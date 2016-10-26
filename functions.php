<?php
namespace SnowIO\ExtendedProductRepository;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Api\Data\AttributeInterface;

/**
 * @return string[]
 */
function getCustomAttributeCodes(ProductInterface $product) : array
{
    return \array_map(function (AttributeInterface $attribute) {
        return $attribute->getAttributeCode();
    }, $product->getCustomAttributes());
}

/**
 * @param string[] $attributeCodes
 */
function getCustomAttributeValues(ProductInterface $product, array $attributeCodes) : array
{
    $values = [];

    foreach ($product->getCustomAttributes() as $customAttribute) {
        $attributeCode = $customAttribute->getAttributeCode();
        if (isset($attributeCodes[$attributeCode])) {
            $values[$attributeCode] = $customAttribute->getValue();
        }
    }

    return $values;
}

/**
 * @param \Magento\Framework\Api\AttributeInterface[] $attributes
 */
function applyCustomAttributes(ProductInterface $product, array $attributes) : ProductInterface
{
    $product = clone $product;

    foreach ($attributes as $attribute) {
        $product->setCustomAttribute($attribute->getAttributeCode(), $attribute->getValue());
    }
}
