<?php
/**
 * Courier class
 * 
 * PHP version 8
 *
 * @category Courier
 * @package  Courier
 * @author   Author <szymon9712@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://localhost/
 */

namespace App;

require '../vendor/autoload.php';

/**
 * Courier class
 * 
 * @category Courier
 * @package  Courier
 * @author   Author <szymon9712@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://localhost/
 */
class Courier
{
    /**
     * Courier contructor
     * 
     * @param string $url                    api url
     * @param string $errorMsgMandatory      error message for param mandatory
     * @param string $errorMsgLength         error message for param max length 
     * @param string $missingMandatoryFields missing parameters with example data
     **/
    public function __construct(
        private readonly string $url = 'https://mtapi.net',
        private string $errorMsgMandatory = '"%s" field is mandatory.',
        private string $errorMsgLength = '"%s" field is mandatory and must be no longer than 30 characters.',
        private array $missingMandatoryFields = ['command' => 'OrderShipment', 'shipper_reference' => '123123123', 'weight' => 0.99]
    ) {
    }

    /**
     * Creates package and returns it's tracking number
     * 
     * @param array $params api parameters
     * @param array $order  shipment details
     * 
     * @return string
     */
    public function newPackage(array $params, array $order): string
    {
        // ADD MISSING MANDATORY PARAMS TO LET METHOD EXEC WITH SUCCESS
        $params = array_merge($params, $this->missingMandatoryFields);

        $this->_validateParams($params, $order);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_buildPayload($params, $order));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $response = json_decode($response);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            if (property_exists($response, 'Shipment') && property_exists($response->Shipment, 'TrackingNumber')) {
                return $response->Shipment->TrackingNumber;
            }
            throw new \Exception(sprintf('Request failed. Error: %s, Error Level: %s', $response->Error, $response->ErrorLevel));
        } else {
            throw new Exception(sprintf('Request failed. Error: %s', curl_error($ch)));
        }
        curl_close($ch);
    }

    /**
     * Creates package label as pdf and saves it on local server
     * 
     * @param string $trackingNumber package tracking number
     * @param string $command        api command parameter
     * @param string $apikey         api key
     * 
     * @return string
     */
    public function packagePDF(string $trackingNumber, string $command, string $apikey): void
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode(
                [
                  'Apikey' => $apikey,
                  'Command' => $command,
                  'Shipment' => [
                    'TrackingNumber' => $trackingNumber
                  ]
                ]
            )
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && property_exists($response, 'Shipment') && property_exists($response->Shipment, 'LabelImage')) {
            file_put_contents(
                'label.pdf',
                base64_decode($response->Shipment->LabelImage)
            );
        } else {
            throw new \Exception(
                sprintf(
                    'Request failed. Error: %s, Error Level: %s',
                    $response->Error,
                    $response->ErrorLevel
                )
            );
        }
    }

    /**
     * Creates package label as pdf and saves it on local server
     * 
     * @param array $params api params
     * @param array $order  package details
     * 
     * @return string
     */
    private function _validateParams(array $params, array $order)
    {
        $errors = [];

        $mandatoryFields = [
          'api_key' => 'Apikey',
          'command' => 'Command',
          'shipper_reference' => 'ShipperReference',
          'service' => 'Service',
          'weight' => 'Weight'
        ];

        foreach ($mandatoryFields as $field => $api_field) {
            if (array_key_exists($field, $params) == false || !isset($params[$field])) {
                $errors[] = sprintf($this->errorMsgMandatory, $field);
            }
        }


        $consignorMandatoryfields = [
          'sender_name' => 'name'
        ];

        $consigneeMandatoryFields = [
          'delivery_fullname' => 'Name',
          'delivery_address' => 'AddressLine1',
          'delivery_city' => 'City',
          'delivery_postalcode' => 'Zip',
          'delivery_country' => 'Country',
          'delivery_phone' => 'Phone',
          'delivery_email' => 'Email'
        ];
        
        foreach ($consigneeMandatoryFields as $field => $api_field) {
            if (array_key_exists($field, $order) == false || !isset($order[$field]) || strlen($order[$field]) > 30) {
                $errors[] = sprintf($this->errorMsgLength, $field);
            }
        }

        if (count($errors) > 0) {
            throw new \InvalidArgumentException(implode(PHP_EOL, $errors));
        }
    }

    /**
     * Builds payload for OrderShipment api request
     * 
     * @param array $params api params
     * @param array $order  package details
     * 
     * @return string
     */
    private function _buildPayload(array $params, array $order): string
    {
        return json_encode(
            [
            'Apikey' => $params['api_key'],
            'Command' => $params['command'],
            'Shipment' => [
              'Weight' => $params['weight'],
              'ShipperReference' => $params['shipper_reference'],
              'Service' => $params['service'],
              'ConsignorAddress' => [
                'Company' => $order['sender_company'],
                'Name' => $order['sender_fullname'],
                'AddressLine1' => $order['sender_address'],
                'City' => $order['sender_city'],
                'Zip' => $order['sender_postalcode'],
                'Email' => $order['sender_email'],
                'Phone' => $order['sender_phone']
              ],
              'ConsigneeAddress' => [
                'Company' => $order['delivery_company'],
                'Name' => $order['delivery_fullname'],
                'AddressLine1' => $order['delivery_address'],
                'City' => $order['delivery_city'],
                'Zip' => $order['delivery_postalcode'],
                'Email' => $order['delivery_email'],
                'Phone' => $order['delivery_phone']
              ]
            ]
            ]
        );
    }
}
