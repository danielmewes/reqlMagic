<?php

require_once("vendor/autoload.php");

require_once("traversal.php");
require_once("queryGraph.php");

// TODO: Hard-coded config
$conn = r\connect("localhost");
$candidates = array(new Candidate(new Term(Term::TYPE_ROW), $conn));
$conn->close();

$numIterations = 50;

for ($i = 0; $i < $numIterations; ++$i) {
    $candidates = computeNextGen($candidates);
    $avgScore = 0.0;
    foreach ($candidates as $c) {
        $avgScore += $c->score;
    }
    $avgScore /= count($candidates);
    echo "Gen " . ($i+2) . ": " . count($candidates) . " candidates. Avg score: " . $avgScore . " Max score: " . $candidates[0]->score . "\n";
}

echo "Top candidates:\n";
echo "Score\tQuery\n";
for ($i = 0; $i < 5; ++$i) {
    echo round($candidates[$i]->score);
    echo "\t";
    echo $candidates[$i]->printedTerm;
    echo "\n";
    echo "\t-> " . print_r($candidates[$i]->results, true);
    echo "\n";
}

