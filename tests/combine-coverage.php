<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;

$filter = new Filter();
$filter->includeDirectory('../code-coverage-data');

$coverage = new CodeCoverage(
    (new Selector())->forLineCoverage($filter),
    $filter
);

//$coverage->start('<name of test>');
//
//// ...
//
//$coverage->stop();


(new HtmlReport())->process($coverage, 'code-coverage-report');
