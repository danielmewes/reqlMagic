<?php

require_once("rdb/rdb.php");
require_once("queryGraph.php");
require_once("scoring.php");

// TODO: Globals...
$population = 500;
$numMutants = 4;

// TODO: Some example inputs. These should be passed in differently.
//$inputs = array(1, 2, 3, 4);
//$outputs = array(array(1, 1), array(2, 4), array(3, 9), array(4, 16));
$inputs = array(array(1, 2), array(5, 5), array(2, 3));
$outputs = array(3, 10, 5);
//$inputs = array(array(1, 2), array(5, 2));
//$outputs = array(array(2, 1), array(2, 5));

class Candidate {
    function __construct($t, $conn) {
        global $inputs;
        global $outputs;
    
        $this->term = $t;
        
        $query = $t->toQuery();
        $this->printedTerm = (string)$query;
        $this->score = 0.0;
        $this->score += scoreQuery($query);
        $this->results = "ERROR";
        try {
            $this->results = r\expr($inputs)->map($query)->run($conn);
            // TODO: This scoring is bad. Also we should check if the result matched exactly
            // to extract valid candidates.
            $numCorrect = 0;
            for ($i = 0; $i < count($inputs); ++$i) {
                if (print_r($outputs[$i], true) == print_r($this->results[$i], true)) {
                    $numCorrect++;
                }
                $this->score += scoreResult($outputs[$i], $this->results[$i]);
            }
            if ($numCorrect == count($inputs)) {
                // Found a valid candidate.
                // Give it an extra score boost.
                $this->score += 500.0;
            }
        } catch (r\RqlUserError $e) {
        } catch (r\RqlServerError $e) {
        }
    }
    var $term;
    var $printedTerm;
    var $score;
    var $results;
}

function mutate($currentCandidates) {
    global $numMutants;

    // TODO: Hard-coded config
    $conn = r\connect("localhost");

    // Take each candidate from the current candidates list and mutate
    // it with a chance propotional to its score.
    $newCandidates = $currentCandidates;
    $maxScore = 0.0;
    foreach ($currentCandidates as $candidate) {
        if ($candidate->score > $maxScore) {
            $maxScore = $candidate->score;
        }
    }
    foreach ($currentCandidates as $candidate) {
        $TOP_LEVEL_MUTATION_CHANCE = 0.5;
        for ($i = 0; $i < $numMutants; ++$i) {
            // Always mutate the top candidate
            $toss = rand(0, $maxScore);
            if ($toss <= $candidate->score) {
                $hasMutated = false;
                $mutant = $candidate->term->createMutant($TOP_LEVEL_MUTATION_CHANCE, $hasMutated);
                if ($hasMutated) {
                    $newCandidates[] = new Candidate($mutant, $conn);
                }
            }
        }
    }
    
    $conn->close();
    
    return $newCandidates;
}

function pruneCandidates($newCandidates) {
    global $population;

    // Sort the new candidates
    usort($newCandidates, function ($a, $b) { if ($a->score > $b->score) return -1; if ($a->score == $b->score) return 0; return 1; });

    // Remove duplicates and prune the low end (so we are left with at most $population)
    $prev = null;
    $result = array();
    foreach ($newCandidates as $candidate) {
        if (count($result) >= $population) {
            break;
        }
        if ($prev != $candidate->printedTerm) {
            $result[] = $candidate;
            $prev = $candidate->printedTerm;
        }
    }

    return $result;
}

class MutationWorker {
    public function __construct($currentCandidates, $randomSeed) {
        $this->currentCandidates = $currentCandidates;
        $this->seed = $randomSeed;
    }
    
    public function start() {
        $this->resultFifo = tempnam("/tmp", "reqlMagic");
        $this->pid = pcntl_fork();
        if ($this->pid == 0) {
            srand($this->seed);
            $this->run();
            exit(0);
        }
    }
    
    public function join() {
        pcntl_waitpid($this->pid, $status);
        // TODO: Using a regular file for this is pretty bad.
        $contents = file_get_contents($this->resultFifo);
        unlink($this->resultFifo);
        $this->newCandidates = unserialize($contents);
    }
    
    public function run() {
        $newCandidates = mutate($this->currentCandidates);
        file_put_contents($this->resultFifo, serialize($newCandidates));
    }

    var $currentCandidates;
    var $seed;
    var $newCandidates;
    var $pid;
    var $resultFifo;
}

function computeNextGen($currentCandidates) {
    $NUM_WORKERS = 4;

    // Split the current population into $NUM_WORKERS parts.
    $threadCandidates = array_fill(0, $NUM_WORKERS, array());
    for ($i = 0; $i < count($currentCandidates); ++$i) {
        $targetThread = $i % $NUM_WORKERS;
        $threadCandidates[$targetThread][] = $currentCandidates[$i];
    }

    // Launch processes to generate mutants
    $workers = array();
    for ($i = 0; $i < $NUM_WORKERS; ++$i) {
        $worker = new MutationWorker($threadCandidates[$i], rand(0, 10000000));
        $worker->start();
        $workers[] = $worker;
    }
    
    // Join the threads back and merge their results
    $newCandidates = array();
    for ($i = 0; $i < $NUM_WORKERS; ++$i) {
        $workers[$i]->join();
        $newCandidates = array_merge($newCandidates, $workers[$i]->newCandidates);
    }

    // Prune candidates
    return pruneCandidates($newCandidates);
}

?>
