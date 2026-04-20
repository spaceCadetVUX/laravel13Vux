<?php

namespace App\Enums;

enum JsonldSchemaType: string
{
    case Product         = 'Product';
    case Article         = 'Article';
    case BreadcrumbList  = 'BreadcrumbList';
    case FaqPage         = 'FAQPage';
    case Organization    = 'Organization';
    case WebSite         = 'WebSite';
    case CollectionPage  = 'CollectionPage';
    case Blog            = 'Blog';
    case AggregateRating = 'AggregateRating'; // embedded in Product — not standalone
    case VideoObject     = 'VideoObject';      // per product video
}
