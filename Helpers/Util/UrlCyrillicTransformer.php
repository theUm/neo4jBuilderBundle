<?php

namespace Nodeart\BuilderBundle\Helpers\Util;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class UrlCyrillicTransformer implements DataTransformerInterface
{

    public $reservedMapping = [
        ' ' => '--',
        '.' => '_dt',
        ':' => '~',
        '/' => '__',
        '?' => '_qm',
        '#' => '_hash',
        '[' => '_lbr',
        ']' => '_rbr',
        '@' => '_at',
        '(' => '_lp',
        ')' => '_rp'
    ];
    public $unReserved = ['-', '.', '_', '~'];

    public function transform($value)
    {
        if ((!is_string($value) && !is_numeric($value)) || empty($value)) {
            return '';
        } else {
            // replace reserved by rfc3986 (https://tools.ietf.org/html/rfc3986#section-2.2) + space char + dot char
            return (strtr($value, $this->reservedMapping));
        }
    }

    public function reverseTransform($value)
    {
        return strtr($value, array_flip($this->reservedMapping));
    }
}