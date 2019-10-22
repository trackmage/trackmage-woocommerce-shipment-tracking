<?php
namespace TrackMage\WordPress\Tests\wpunit\Endpoint;


use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Webhook\Mappers\ShipmentsMapper;
use TrackMage\WordPress\Synchronization\ChangesDetector;

class ShipmentsMapperTest extends WPTestCase
{

    use GuzzleMockTrait;

    const SOURCE = 'wp';
    const TM_SHIPMENT_ID = '1010';
    const TM_WS_ID = '1001';
    const TEST_TRACKING_NUMBER = 'UPS-ABCDEFG012345';
    const TEST_CARRIER = 'UPS';
    const TEST_STATUS = 'in_transit';

    /** @var \WpunitTester */
    protected $tester;

    /** @var ShipmentRepository */
    private $shipmentRepo;

    /** @var ShipmentsMapper */
    private $shipmentsMapper;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $endpoint = Plugin::instance()->getEndpoint();
        $endpoint->setDisableEvents(true);

        WC()->init();
    }

    protected function _before()
    {
        add_option('trackmage_workspace', self::TM_WS_ID);

        $this->shipmentRepo = Plugin::instance()->getShipmentRepo();
        $this->shipmentsMapper = new ShipmentsMapper($this->shipmentRepo, self::SOURCE);
    }


    public function testShipmentIsFullyUpdated() {
        //GIVEN

        $trackMageId = rand(10000,99999);
        //programmatically create a shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $dataBefore = [
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'status' => self::TEST_STATUS
        ];
        $wcShipment = $this->shipmentRepo->insert(array_merge([
            'order_id' => $wcOrderId,
            'trackmage_id' => $trackMageId,
        ],$dataBefore));
        $wcShipmentId = $wcShipment['id'];

        $item = [
            'entity'        => 'shipments',
            'data'          =>
                [
                    'id'                     => $trackMageId,
                    'trackingNumber'         => 'DHL0123456789',
                    'status'                 => 'returned',
                    'originCarrier'          => 'DHL',
                    'workspace'              => '/workspaces/'.self::TM_WS_ID,
                    'externalSource'         => self::SOURCE,
                    'externalSyncId'         => $wcShipmentId
                ],
            'event'         => 'update',
            'updatedFields' =>
                [
                    0 => 'status',
                    1 => 'originCarrier',
                    2 => 'trackingNumber'
                ],
        ];

        //WHEN everything is OK
        $this->shipmentsMapper->handle($item);

        //THEN
        $shipmentAfter = $this->shipmentRepo->find($wcShipmentId);
        $dataAfter = [
            'tracking_number' => $shipmentAfter['tracking_number'],
            'carrier' => $shipmentAfter['carrier'],
            'status' => $shipmentAfter['status']
        ];
        $differences = array_intersect_assoc($dataBefore, $dataAfter);

        self::assertEquals(count($differences),0);
    }

    public function testShipmentCanBeHandled() {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);
        $trackMageId = rand(10000,99999);
        //programmatically create a shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => $trackMageId
        ]);
        $wcShipmentId = $wcShipment['id'];

        $item = [
            'entity'        => 'shipments',
            'data'          =>
                [
                    'id'                     => $trackMageId,
                    'trackingNumber'         => 'DHL0123456789',
                    'status'                 => 'delivered',
                    'originCarrier'          => 'dhl',
                    'workspace'              => '/workspaces/'.self::TM_WS_ID,
                    'externalSource'         => self::SOURCE,
                    'externalSyncId'         => $wcShipmentId
                ],
            'event'         => 'update',
            'updatedFields' =>
                [
                    0 => 'status',
                    1 => 'originCarrier'
                ],
        ];

        //WHEN workspace is wrong
        $wrongItem = $item;
        $wrongItem['data']['workspace'] = '/workspaces/999999';
        $result = $this->shipmentsMapper->handle($wrongItem);
        self::assertFalse($result);

        //WHEN external source is wrong
        $wrongItem = $item;
        $wrongItem['data']['externalSource'] = 'wp-test0001';
        $result = $this->shipmentsMapper->handle($wrongItem);
        self::assertFalse($result);

        //WHEN unknown shipment
        $wrongItem = $item;
        $wrongItem['data']['externalSyncId'] = '99999';
        $wrongItem['data']['id'] = rand(1000,9999);
        $result = $this->shipmentsMapper->handle($wrongItem);
        self::assertFalse($result);

    }
}