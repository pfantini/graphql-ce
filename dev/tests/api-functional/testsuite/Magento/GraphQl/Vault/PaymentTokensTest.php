<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Vault;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class PaymentTokensTest extends GraphQlAbstract
{
    /**
     * Verify store payment token with valid credentials
     *
     * @magentoApiDataFixture Magento/Vault/_files/payment_active_tokens.php
     */
    public function testPaymentTokenWithValidCredentials()
    {
        $query
            = <<<QUERY
{
  paymentTokens {
    public_hash
    payment_method_code
    type
    expires_at
    details {
      code
      value
    }
  }
}
QUERY;
        $userName = 'customer@example.com';
        $password = 'password';
        /** @var CustomerTokenServiceInterface $customerTokenService */
        $customerTokenService = ObjectManager::getInstance()
            ->get(\Magento\Integration\Api\CustomerTokenServiceInterface::class);
        $customerToken = $customerTokenService->createCustomerAccessToken($userName, $password);
        $headerMap = ['Authorization' => 'Bearer ' . $customerToken];
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = ObjectManager::getInstance()->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->get($userName);
        $response = $this->graphQlQuery($query, [], '', $headerMap);
        $this->assertTrue(is_array($response['paymentTokens']), "paymentTokens field must be of an array type.");
        $this->assertEquals(
            $this->getPaymentTokenAmountFroCustomer($customer->getId()),
            count($response['paymentTokens'])
        );
        $list = $response['paymentTokens'];

        $this->assertEquals('H123456789', $list[0]['public_hash']);
        $this->assertEquals('H987654321', $list[1]['public_hash']);
        $this->assertEquals('H1122334455', $list[2]['public_hash']);

        $this->assertEquals('code_first', $list[0]['payment_method_code']);
        $this->assertEquals('code_second', $list[1]['payment_method_code']);
        $this->assertEquals('code_third', $list[2]['payment_method_code']);

        $this->assertEquals('card', $list[0]['type']);
        $this->assertEquals('card', $list[1]['type']);
        $this->assertEquals('account', $list[2]['type']);

        $this->assertIsDetailsArray($list);
        $this->assertTokenDetails(
            ['type' => 'VI', 'maskedCC' => '9876', 'expirationDate' => '12/2020'],
            $list[0]['details']
        );
        $this->assertTokenDetails(
            ['type' => 'MC', 'maskedCC' => '4444', 'expirationDate' => '12/2030'],
            $list[1]['details']
        );
        $this->assertTokenDetails(
            ['type' => 'DI', 'maskedCC' => '0001', 'expirationDate' => '12/2040'],
            $list[2]['details']
        );
    }

    /**
     * Verify store payment token without credentials
     */
    public function testPaymentTokenWithoutCredentials()
    {
        $query
            = <<<QUERY
{
  paymentTokens {
    public_hash
    payment_method_code
    type
    created_at
    expires_at
    details {
      code
      value
    }
  }
}
QUERY;
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('GraphQL response contains errors:' . ' ' .
            'A guest customer cannot access resource "store_payment_token".');
        $this->graphQlQuery($query);
    }

    /**
     * Verify is details is array
     *
     * @param array $response
     */
    private function assertIsDetailsArray(array $response)
    {
        foreach ($response as $token) {
            $this->assertTrue(is_array($token['details']));
        }
    }

    /**
     * Verify token details
     *
     * @param array $expected
     * @param array $response
     */
    private function assertTokenDetails(array $expected, array $response)
    {
        foreach ($response as $details) {
            $this->assertEquals($expected[$details['code']], $details['value']);
        }
    }

    /**
     * Get amount of customer payment token
     *
     * @param int $customerId
     * @return int
     */
    private function getPaymentTokenAmountFroCustomer($customerId)
    {
        /** @var \Magento\Vault\Model\PaymentTokenManagement $paymentTokenManagementInterface */
        $paymentTokenManagementInterface = ObjectManager::getInstance()
            ->get(PaymentTokenManagementInterface::class);
        return count($paymentTokenManagementInterface->getVisibleAvailableTokens($customerId));
    }
}
