<?php

namespace SnowIO\ExtendedProductRepository\Test\Integration\Model;

use Exception;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductExtensionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Api\Data\AttributeFrontendLabelInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionLabelInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

class ConfigurableProductMappingTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ObjectManagerInterface */
    private $objectManager;

    /** @var  ProductRepositoryInterface */
    private $productRepository;

    /** @var  ProductAttributeRepositoryInterface */
    private $attributeRepository;

    /** @var  ExtensionAttributesFactory */
    private $extensionAttributesFactory;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->attributeRepository = $this->objectManager->get(ProductAttributeRepositoryInterface::class);
        $this->extensionAttributesFactory = $this->objectManager->get(ExtensionAttributesFactory::class);
    }
    
    public function setUp()
    {
        //create attributes color and size and assign them the same attribute set id
        $optionAttributes = [
            'test_colour' => [ // options
                $this->objectManager->create(AttributeOptionInterface::class)->setStoreLabels([
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(0)
                        ->setLabel('Red'),
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(1)
                        ->setLabel('Rot'),
                ]),
                $this->objectManager->create(AttributeOptionInterface::class)->setStoreLabels([
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(0)
                        ->setLabel('Blue'),
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(1)
                        ->setLabel('Blau'),
                ]),
            ],
            'test_size' => [
                $this->objectManager->create(AttributeOptionInterface::class)->setStoreLabels([
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(0)
                        ->setLabel('Small'),
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(1)
                        ->setLabel('Klein'),
                ]),
                $this->objectManager->create(AttributeOptionInterface::class)->setStoreLabels([
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(0)
                        ->setLabel('large'),
                    $this->objectManager->create(AttributeOptionLabelInterface::class)
                        ->setStoreId(1)
                        ->setLabel('groß'),
                ]),
            ],
        ];


        $this->persistAttributeOptions('int', 'select', $optionAttributes);

        //create a test product that the configurable products will link to
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        /** @var ProductInterface$productFactory */
        $product = $this->objectManager->get(ProductInterface::class);
        /** @var \Magento\Catalog\Model\Product $product */
        $product->setTypeId('simple');
        $product->setExtensionAttributes(
            $this->objectManager
                ->get(ProductExtensionFactory::class)
                ->create());
        $product->setSku('test-product');
        $product->setName('Test Product');
        $product->setPrice(1.00);
        $product->setAttributeSetId(4);
        $productRepository->save($product);
    }

    /**
     * @dataProvider getStandardCaseTestData
     */
    public function testStandardCase(ProductInterface $configurableProduct)
    {
        $this->productRepository->save($configurableProduct);
        $output = $this->productRepository->get($configurableProduct->getSku());
        $inputProductExtensionAttributes  = $configurableProduct->getExtensionAttributes();
        $inputConfigurableProductOptions  = $inputProductExtensionAttributes->getConfigurableProductOptions();
        $outputProductExtensionAttributes = $output->getExtensionAttributes();
        $outputConfigurableProductOptions = $outputProductExtensionAttributes->getConfigurableProductOptions();

        $inputAttributeIds = [];
        foreach ($inputConfigurableProductOptions as $configurableProductOption) {
            $inputAttributeIds[] = $this->getAttributeIdFromCode(
                $configurableProductOption
                    ->getExtensionAttributes()
                    ->getAttributeCode()
            );
        }

        $outputAttributeIds = [];
        foreach ($outputConfigurableProductOptions as $outputConfigurableProductOption) {
            $outputAttributeIds[] = $outputConfigurableProductOption->getAttributeId();
        }

        $this->assertEquals(0, count(array_diff($inputAttributeIds, $outputAttributeIds)));

        $inputProductIds = array_map(function (string $sku) {
            return $this->getProductIdFromSku($sku);
        }, $inputProductExtensionAttributes->getConfigurableProductLinkedSkus());


        $outputProductIds = $outputProductExtensionAttributes->getConfigurableProductLinks();

        $this->assertEquals(0, count(array_diff($inputProductIds, $outputProductIds)));
    }

    public function getStandardCaseTestData()
    {
        return [
            [ //test case 1
                $this->objectManager->create(ProductInterface::class)
                    ->setSku('test-configurable-product-red')
                    ->setTypeId('configurable')
                    ->setName('Test Configurable')
                    ->setAttributeSetId(4)
                    ->setExtensionAttributes(
                        $this->extensionAttributesFactory->create(\Magento\Catalog\Api\Data\ProductInterface::class)
                            ->setConfigurableProductOptions(
                                [
                                    $this->objectManager
                                        ->create(\Magento\ConfigurableProduct\Api\Data\OptionInterface::class)
                                        ->setExtensionAttributes(
                                            $this->extensionAttributesFactory
                                                ->create(\Magento\ConfigurableProduct\Api\Data\OptionInterface::class)
                                                ->setAttributeCode('test_size')
                                        ),
                                    $this->objectManager
                                        ->create(\Magento\ConfigurableProduct\Api\Data\OptionInterface::class)
                                        ->setExtensionAttributes(
                                            $this->extensionAttributesFactory
                                                ->create(\Magento\ConfigurableProduct\Api\Data\OptionInterface::class)
                                                ->setAttributeCode('test_colour')
                                        ),
                                ]
                            )
                            ->setConfigurableProductLinkedSkus(['test-product'])
                    )

            ]
        ];
    }

    public function tearDown()
    {
        $attributesCodes = ['test_colour', 'test_size'];
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        try {
            if (null !== $productRepository->get('test-product')->getId()) {
                $productRepository->deleteById('test-product');
            }
            /** @var ProductAttributeRepositoryInterface $attributeRepository */
            $attributeRepository = $this->objectManager->get(ProductAttributeRepositoryInterface::class);
            foreach ($attributesCodes as $attributesCode) {
                $attributeRepository->deleteById($attributesCode);
            }
        } catch (Exception $e) {
        }
    }


    private function persistAttributeOptions(
        string $backendType,
        string $frontendInput,
        array $optionAttributes
    ) {
        /** @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $this->objectManager->get(ProductAttributeRepositoryInterface::class);
        /** @var ProductAttributeManagementInterface $attributeManager */
        $attributeManager = $this->objectManager->get(ProductAttributeManagementInterface::class);
        foreach ($optionAttributes as $attributeCode => $attributeOptions) {
            /** @var \Magento\Catalog\Api\Data\ProductAttributeInterface $productAttribute */
            $productAttribute = $this->objectManager->create(ProductAttributeInterface::class);
            $productAttribute->setAttributeCode($attributeCode);
            $productAttribute->setBackendType($backendType);
            $productAttribute->setFrontendInput($frontendInput);
            /** @var AttributeFrontendLabelInterface $frontEndLabelsDefaultStore */
            $frontEndLabelsDefaultStore = $this->objectManager->create(AttributeFrontendLabelInterface::class);
            $frontEndLabelsDefaultStore->setLabel("$attributeCode Label");
            $frontEndLabelsDefaultStore->setStoreId(0);
            /** @var AttributeFrontendLabelInterface $frontEndLabelsDefaultStore */
            $frontEndLabelsTestStore = $this->objectManager->create(AttributeFrontendLabelInterface::class);
            $frontEndLabelsTestStore->setLabel("$attributeCode Etikette");
            $frontEndLabelsTestStore->setStoreId(1);
            $productAttribute->setFrontendLabels([$frontEndLabelsDefaultStore, $frontEndLabelsTestStore]);
            $productAttribute->setSourceModel('eav/entity_attribute_source_table');
            $productAttribute->setIsUserDefined(true);
            $productAttribute->setOptions($attributeOptions);
            $attributeRepository->save($productAttribute);
            $attributeManager->assign(4, 7, $attributeCode, 1);
        }
    }

    private function getProductIdFromSku(string $sku)
    {
        $productId = $this->productRepository->get($sku)->getId();

        if ($productId === null) {
            throw new \RuntimeException('Product id does not exist');
        }

        return $productId;
    }

    private function getAttributeIdFromCode(string $attributeCode)
    {
        $attributeCode = $this->attributeRepository->get($attributeCode)->getAttributeId();
        if ($attributeCode === null) {
            throw new \RuntimeException('Attribute code does not exist');
        }
        return $attributeCode;
    }
}
