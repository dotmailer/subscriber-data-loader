<?php

namespace Dotdigitalgroup\Data\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Newsletter\Model\ResourceModel\Subscriber;
use \Magento\Framework\App\State;
use \Magento\Store\Model\StoreManagerInterface;

class GenerateSubscribersFromSalesData extends Command
{
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var \Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory
     */
    private $orderCollection;

    /**
     * @var Subscriber
     */
    private $subscriberResource;

    /**
     * GenerateSubscribersFromSalesData constructor.
     *
     * @param CollectionFactory $orderCollection
     * @param Subscriber $subscriberResource
     * @param State $state
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CollectionFactory $orderCollection,
        Subscriber $subscriberResource,
        State $state,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->orderCollection = $orderCollection;
        $this->subscriberResource = $subscriberResource;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ddg:subscribers:generate')
            ->setDescription('Generate subscribers from guest orders');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
        $collection = $this->orderCollection->create()
            ->addFieldToSelect(['store_id', 'customer_email'])
            ->addFieldToFilter('customer_id', ['null' => true]);

        $output->writeln('<info>Number of subscribers generated :<info>');

        if ($collection->getSize()) {
           $output->writeln('<info>' . $this->insertSubscribersFromSalesCollection($collection) . '<info>');
        } else {
            $output->writeln('<info>No guest orders found to generate subscriber. Make sure to first generate data using "bin/magento setup:perf:generate-fixtures path-to-profile" than run this command again. <info>');
        }
    }

    /**
     * @param $collection
     *
     * @return int
     */
    private function insertSubscribersFromSalesCollection($collection)
    {
        $subscribers = [];
        $contacts = [];
        $connection = $this->subscriberResource->getConnection();
        foreach ($collection as $item) {
            $subscribers[] = [
                "store_id" => $item->getStoreId(),
                "subscriber_status" => 1,
                "subscriber_email" => $item->getCustomerEmail()
            ];
            $contacts[] = [
                "customer_id" => 0,
                "website_id" => 1,
                "store_id" => $this->storeManager->getStore($item->getStoreId())->getWebsiteId(),
                "email" => $item->getCustomerEmail(),
                "is_subscriber" => 1,
                "subscriber_status" => 1
            ];
        }
        $connection->insertMultiple($this->subscriberResource->getTable("email_contact"), $contacts);
        return $connection->insertMultiple($this->subscriberResource->getMainTable(), $subscribers);
    }
}