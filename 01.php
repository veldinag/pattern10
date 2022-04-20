<?php
    class ExpNode {

        public $data;
        public $left;
        public $right;

        public function __construct($cell) {
            $this->data = $cell['data'];
            $this->left = $cell['left'];
            $this->right = $cell['right'];
        }
    }

    class ExpTree {

        const DIGITS = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "."];
        const OPERATIONS = ["^", "*", "/", "+", "-"];
        public $params; // массив с параметрами для вычисления резульатат выражения
        public $expression; // исходное выражение
        private $expArray; // массив с литералами
        private $root; // бинарное дерево, созданное
        private $expQueue; // массив с очередью выражений
        private $brackets; // количество пар скобок

        public function __construct ($expression) {
            $this->expression = $expression;
            $this->expArray = $this->prepareExpArray();
            $this->expQueue = [];
            $this->setExpQueue($this->expArray);
            $this->createTree($this->root, $this->expQueue[count($this->expQueue) - 1]);
        }

        public function getExpression() {
            return $this->expression;
        }

        private function calculate($params, $operation) {
            switch ($operation) {
                case "+": return round($params[0] + $params[1], 2);
                case "-": return round($params[0] - $params[1], 2);
                case "/": return round($params[0] / $params[1], 2);
                case "*": return round($params[0] * $params[1], 2);
                case "^": return round(pow($params[0], $params[1]), 2);
            }
        }

        // функция преобразования строки с выражением в массив с литералами

        private function prepareExpArray() {
            // удаляем пробелы
            $array = str_split(preg_replace('/\s/', '', $this->expression));
            $result = [];
            $br_couple = [];
            for ($i=0; $i<count($array); $i++) {
                // преобразуем отдельные цифры в соседних ячейках массива в числа
                if (in_array($array[$i], self::DIGITS) or $array[$i] == ".") {
                    $digit = $array[$i];
                    for ($j=$i+1; $j<count($array); $j++) {
                        if (in_array($array[$j], self::DIGITS)) {
                            $digit .= $array[$j];
                            $i++;
                        } else {
                            break;
                        }
                    }
                    array_push($result, $digit);
                    // обработка скобок
                } elseif ($array[$i] === "(") {
                    array_push($br_couple, true);
                    array_push($result, "__".count($br_couple));
                } elseif ($array[$i] === ")") {
                    $k = count($br_couple) - 1;
                    while ($k >= 0) {
                        if ($br_couple[$k]) {
                            $br_couple[$k] = false;
                            $br=$k+1;
                            array_push($result, "__$br");
                            break;
                        }
                        $k--;
                    }
                } else {
                    array_push($result, $array[$i]);
                }
            }
            $this->brackets=count($br_couple);
            return $result;
        }

        // обработка частей выражения с математическими операторами

        private function mathOperHandler(&$array, $operation) {
            $start_q = 0;
            $end_q = 0;
            for ($i=0; $i<count($array); $i++) {
                if ($array[$i] == $operation) {
                    $queue['left'] = $array[$i-1];
                    $queue['data'] = $array[$i];
                    $queue['right'] = $array[$i+1];
                    array_push($this->expQueue, $queue);
                    $array[$i+1] = '-node_'.(count($this->expQueue)-1);
                    $start_q = $i-1;
                    $end_q = $i+1;
                    break;
                }
            }
            if ($start_q != 0 or $end_q != 0) {
                array_splice($array, $start_q, $end_q-$start_q);
            }
        }

        // формирование массива с очередью из коротких математических выражений

        private function setExpQueue(&$array) {
            while (count($array) > 1) {
                $start_q = 0;
                $end_q = 0;
                while ($this->brackets > 0) {
                    $br = $this->brackets;
                    $is_bracket = false;
                    $bracketArray = [];
                    // цикл для обработки выражений внутри скобок
                    for ($i = 0; $i < count($array); $i++) {
                        if ($array[$i] == "__$br") {
                            if (!$is_bracket) {
                                $is_bracket = true;
                                $start_q = $i;
                            } else {
                                $is_bracket = false;
                                $end_q = $i;
                                $array[$i] = '-node_' . (count($this->expQueue) + (($end_q - $start_q - 4) / 2));
                            }
                        } else {
                            if ($is_bracket) {
                                array_push($bracketArray, $array[$i]);
                            }
                        }
                    }
                    if ($start_q != 0 or $end_q != 0) {
                        array_splice($array, $start_q, $end_q - $start_q);
                    }
                    $this->setExpQueue($bracketArray);
                    --$br;
                    $this->brackets = $br;
                }
                foreach (self::OPERATIONS as $operation) {
                    $this->mathOperHandler($array, $operation);
                }
            }
        }

        // формирование дерева

        private function createTree(&$current, $cell) {
            $end = false;
            $node = new ExpNode($cell);
            $current = $node;
            while(!$end) {
                if(is_string($current->left) and strpos($current->left, "node")) {
                    $arr = explode("_", $current->left);
                    $nextNode = (int)$arr[1];
                    $this->createTree($current->left, $this->expQueue[$nextNode]);
                } elseif(is_string($current->right) and strpos($current->right, "node")) {
                    $arr = explode("_", $current->right);
                    $nextNode = (int)$arr[1];
                    $this->createTree($current->right, $this->expQueue[$nextNode]);
                } else {
                    $end = true;
                }
            }
            return $end;
        }

        // обход дерева от листьев к корню

        private function treeTraversal(ExpNode  &$node) {
            while (!is_numeric($node)) {
                if (is_object($node->left)) {
                    $this->treeTraversal($node->left);
                } elseif (is_object($node->right)) {
                    $this->treeTraversal($node->right);
                } elseif (!is_numeric($node->left)) {
                    $node->left = $this->params[$node->left];
                } elseif (!is_numeric($node->right)) {
                    $node->right = $this->params[$node->right];
                } else {
                    $node = $this->calculate([(float)$node->left, (float)$node->right], $node->data);
                }
            }
            return $node;
        }

        // вычисляем результат выражения с заданными параметрами

        public function getResult() {
            $result = clone $this->root;
            return $this->treeTraversal($result);
        }
    }

    $expression = "(x + 47) ^ 2 + 7 * y - z";
    $tree = new ExpTree($expression);
    $tree->params = [
        "x" => 2,
        "y" => 3,
        "z" => 21
    ];

    echo $tree->expression;
    echo "<br>";
    
    echo "<pre>";
    print_r($tree->params);
    echo "</pre>";

    echo "<br>";

    echo $tree->getResult();