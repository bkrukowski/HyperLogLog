<?php
include __DIR__ . '/../vendor/autoload.php';

ini_set('memory_limit','512M');

function testHeader($title)
{
    echo '-----------------------------------------------------'.PHP_EOL;
    echo $title . PHP_EOL;
    echo '-----------------------------------------------------'.PHP_EOL;
    echo '|'.implode('|',map_pad(array('Actual','Estimated','Total','Error'))).'|' . PHP_EOL;
    echo '-----------------------------------------------------'.PHP_EOL;
}

function errorLine($actual, $estimated)
{
    return number_format((($actual - $estimated) / $actual) * 100, 4) . '%';
}

function map_pad($array)
{
    return array_map(function($val){return str_pad($val, 12, ' ', STR_PAD_BOTH);}, $array);
}

function printResults($resultsArray)
{
    foreach($resultsArray as $results)
    {
        $results[] = errorLine($results[0], $results[1]);

        echo implode(" " , map_pad($results));

        echo PHP_EOL;
    }

}

function fileResults($file, $resultsArray)
{
    foreach($resultsArray as $results)
    {
        file_put_contents($file, implode("\t" , $results) . PHP_EOL, FILE_APPEND);
    }
}

if($_SERVER['argc'] < 4)
{
    die('Usage: ' . $_SERVER['argv'][0] . ' start end pValue [number_of_tests:10]' . PHP_EOL);
}

$pValue = $_SERVER['argv'][3];
$testMin = $_SERVER['argv'][1];
$testMax = $_SERVER['argv'][2];
$tests = isset($_SERVER['argv'][4]) ? $_SERVER['argv'][4] : 10;

$print = true;
$verbose = false;
$filename = __DIR__ . '/data/hyperloglog/'.$testMin . '-' .$testMax.'-p'.$pValue.'.'.date('Y-m-d_h-i-s').'.csv';

file_put_contents($filename,'');

for($i = $testMin; $i <= $testMax; $i += $block)
{
    echo "Running $i..." . PHP_EOL;

    $block = pow(10, max(0,floor(log10($i))));

    $test = new Test($i, $pValue);

    $test->test($tests);

    if($print)
    {
        if($verbose) {
            testHeader('Tested: ' . $i);
            printResults($test->results());
        }
        testHeader('Average: ' . $i);
        printResults(array($test->averages()));
    }

    fileResults($filename, $test->results());

    echo "Tested $i\r";
}

echo PHP_EOL;



class Test {

    private $i;

    private $pValue;

    private $average = array(0,0,0);

    private $results = array();

    public function __construct($i, $pValue = 14)
    {
        $this->i = $i;

        $this->pValue = $pValue;
    }

    private function random()
    {
        $start = 100000000;

        return mt_rand($start, $start + 2 * $this->i);
    }

    public function test($repeat = 100)
    {
        while($repeat--)
        {
            $ll = new HyperLogLog\Basic($this->pValue);

            $i = 100000000 + $this->random();

            $r = mt_rand(1,4);

            $end = $i + ($this->i * $r);

            while($i <= $end)
            {
                $ll->add($i += $r);
            }

            $this->average[0] += $this->i;

            $count = $ll->count();

            $this->average[1] += $count;

            $this->average[2] += $this->i;

            $this->results[] = array($this->i, $count, $this->i);
        }
    }

    public function averages()
    {
        return $this->average;
    }

    public function results()
    {
        return $this->results;
    }
}