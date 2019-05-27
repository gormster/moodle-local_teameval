<?php

namespace local_teameval;

define('SQL_QUERY_DELETE', 99);

class polymorph_transaction {

    private $transaction;
    private $class;
    private $polytype;
    private $id;
    private $querytype;

    public function __construct($transaction, $class, $polytype, $id, $querytype) {
        $this->transaction = $transaction;
        $this->class = $class;
        $this->polytype = $polytype;
        $this->id = $id;
        $this->querytype = $querytype;
    }

    public function __get($name) {
        return $this->$name;
    }

    public function __set($name, $value) {
        if ($name == 'id') {
            if ($this->querytype == SQL_QUERY_INSERT && empty($this->id)) {
                $this->id = $value;
            } else {
                throw new coding_exception('Cannot update ID if already set or on non-insert transaction '.$name);
            }
        } else {
            throw new coding_exception('Invalid property '.$name);
        }
    }

}
