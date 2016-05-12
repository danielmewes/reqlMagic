<?php

function scoreResult($reference, $res) {
    $SAME_TYPE_BONUS = 100.0;
    $SAME_SIZE_BONUS = 10.0;

    $score = 0.0;
    
    if (is_numeric($reference) && is_numeric($res)) {
        $score += $SAME_TYPE_BONUS;
        $score += 100.0 / (1.0 + abs($reference - $res));
    } else if (is_array($reference) && is_array($res)) {
        $score += $SAME_TYPE_BONUS;
        if (count($reference) == count($res)) {
            $score += $SAME_SIZE_BONUS;
        }
        $subscores = 0.0;
        for ($i = 0; $i < count($reference) && $i < count($res); ++$i) {
            $subscores += scoreResult($reference[$i], $res[$i]);
        }
        $subscores /= max(count($reference), count($res));
        $score += $subscores;
    }
    // TODO: Implement comparisons for other types
    
    return $score;
}

function scoreQuery($query) {
    // TODO: Do something smarter, like specifically penalize constants
    return 10.0 / (1.0 + strlen((string)$query));
}

?>
