<?php

require_once("rdb/rdb.php");

class Term {
    const TYPE_ZERO = 0;
    const TYPE_ONE = 1;
    const TYPE_ADD = 2;
    const TYPE_SUB = 3;
    const TYPE_MUL = 4;
    const TYPE_DIV = 5;
    const TYPE_ROW = 6;
    const TYPE_ARRAY = 7;
    const TYPE_NTH = 8;
    const END_TYPE = 9;

    // Defaults constructs a term of the given type
    public function __construct($type) {
        $this->type = $type;
        switch ($type) {
            case self::TYPE_ZERO:
            case self::TYPE_ONE:
            case self::TYPE_ROW:
                $this->args = array();
                break;
            case self::TYPE_ARRAY:
                $this->args = array(new Term(self::TYPE_ONE));
                break;
            case self::TYPE_NTH:
                $this->args = array(new Term(self::TYPE_ARRAY), new Term(self::TYPE_ZERO));
                break;
            case self::TYPE_ADD:
            case self::TYPE_SUB:
            case self::TYPE_MUL:
            case self::TYPE_DIV:
                $this->args = array(new Term(self::TYPE_ONE), new Term(self::TYPE_ONE));
                break;
            // TODO: Add user constants extracted from the goal query etc
        }
    }
    
    public function toQuery() {
        switch ($this->type) {
            case self::TYPE_ZERO:
                return r\expr(0);
            case self::TYPE_ONE:
                return r\expr(1);
            case self::TYPE_ADD:
                return $this->args[0]->toQuery()->add($this->args[1]->toQuery());
            case self::TYPE_SUB:
                return $this->args[0]->toQuery()->sub($this->args[1]->toQuery());
            case self::TYPE_MUL:
                return $this->args[0]->toQuery()->mul($this->args[1]->toQuery());
            case self::TYPE_DIV:
                return $this->args[0]->toQuery()->div($this->args[1]->toQuery());
            case self::TYPE_ROW:
                return r\row();
            case self::TYPE_ARRAY:
                return r\expr(array($this->args[0]->toQuery()));
            case self::TYPE_NTH: {
                return $this->args[0]->toQuery()->nth($this->args[1]->toQuery());
            }
        }
    }
    
    public function createMutant($chance, &$anythingMutated) {
        $ARG_DROPOFF = 0.7;
    
        $anythingMutated = false;
        $newTerm = null;
        
        $toss = rand(0, 1000) / 1000;
        if ($toss <= $chance) {
            $anythingMutated = true;
            // Decide what to do
            $toss2 = rand(0, 99);
            if ($toss2 < 20 && count($this->args) > 0) {
                // Drop this term, keeping one of its arguments instead.
                $toss3 = rand(0, 1);
                // Keep the first argument? (increased chance)
                if ($toss3 == 0) {
                    $newTerm = $this->args[0];
                } else {
                    // Pick a random argument
                    $toss4 = rand(0, count($this->args) - 1);
                    $newTerm = $this->args[$toss4];
                }
            } else if ($toss2 < 70) {
                // Create a new term, passing this in as one of its arguments.
                $toss3 = rand(0, self::END_TYPE - 1);
                $newTerm = new Term($toss3);
                if (count($newTerm->args) > 0) {
                    $toss4 = rand(0, count($newTerm->args) - 1);
                    $newTerm->args[$toss4] = $this;
                }
            } else {
                // Change the type of this term, but keep our arguments
                // intact as far as possible.
                $toss3 = rand(0, self::END_TYPE - 1);
                $newTerm = new Term($toss3);
                for ($i = 0; $i < count($this->args) && $i < count($newTerm->args); ++$i) {
                    $newTerm->args[$i] = $this->args[$i];
                }
            }
        } else {
            $newTerm = $this;
        }
        
        //Give the args a chance to mutate...
        $argChance = $chance * $ARG_DROPOFF;
        $mutatedArgs = array();
        foreach ($newTerm->args as $a) {
            $subMutated = false;
            $mutatedArgs[] = $a->createMutant($argChance, $subMutated);
            if ($subMutated) {
                $anythingMutated = true;
            }
        }
        $newTerm = new Term($newTerm->type);
        $newTerm->args = $mutatedArgs;

        return $newTerm;
    }

    private $type;
    private $args = array();
}

?>
