<?php

use local_searchable\searchable;

class local_searchable_search_testcase extends advanced_testcase {

    public function test_one_tag() {

        $this->resetAfterTest(true);

        searchable::set_weight('testtype',1,'testtag',1.0);

        $results = searchable::results('testtype', ['testtag']);

        $this->assertEquals(1, count($results));

        $firstResult = current($results);

        $this->assertEquals(1, $firstResult->objectid);
        $this->assertEquals(['testtag'], $firstResult->tags);
        $this->assertEquals(1.0, $firstResult->weight);

    }

    public function test_search_strings() {

        $this->resetAfterTest(true);

        $objects = [
            1 => "Scala is an acronym for 'Scalable Language'. This means that Scala grows with you. You can play with it by typing one-line expressions and observing the results.",
            2 => "Python is a programming language that lets you work more quickly and integrate your systems more effectively.",
            3 => "PHP is a popular general-purpose scripting language that is especially suited to web development. Fast, flexible and pragmatic, PHP powers everything from your blog to the most popular websites in the world."
        ];

        foreach($objects as $k => $v) {
            preg_match_all("/\b[a-zA-Z0-9]+?\b/", $v, $matches);
            $weights = [];
            foreach($matches[0] as $word) {
                $tag = strtolower($word);

                if (!isset($weights[$tag])) {
                    $weights[$tag] = 0;
                }
                $weights[$tag] += 1;
            }

            foreach($weights as $tag => $weight) {
                searchable::set_weight('testtype', $k, $tag, $weight);
            }
        }

        $results = searchable::results('testtype', ['language']);

        $this->assertEquals(3, count($results));

        $results = searchable::results('testtype', ['programming']);

        $this->assertEquals(1, count($results));

        $firstResult = current($results);

        $this->assertEquals(2, $firstResult->objectid);

        $results = searchable::results('testtype', ['quickly', 'fast']);

        $this->assertEquals(2, count($results));

        //TODO: these should come back in a predictable order
        //perhaps the order of tags?

        $results = searchable::results('testtype', ['on'], true);

        $this->assertEquals(2, count($results));

        $firstResult = current($results);

        $this->assertEquals(1, $firstResult->objectid);

        $this->assertEquals(3, $firstResult->weight);

        // use array union to make sure tag numbers are the same
        $this->assertEquals(count(['acronym', 'one', 'expressions'] + $firstResult->tags), 3);

        // this should be Python
        $secondResult = next($results);

        $this->assertEquals(2, $secondResult->objectid);

        $this->assertEquals(1, $secondResult->weight);

        $this->assertEquals(['python'], $secondResult->tags);

    }

    public function test_search_order() {

        $this->resetAfterTest(true);

        $objects = [

            1 => ['one', 'two', 'one', 'three', 'one', 'two'],
            2 => ['two', 'two', 'two', 'one', 'one'],
            3 => ['one', 'two', 'three', 'four', 'five']

        ];

        foreach($objects as $k => $v) {

            $weights = [];
            foreach($v as $tag) {
                if (!isset($weights[$tag])) {
                    $weights[$tag] = 0;
                }
                $weights[$tag] += 1;
            }

            foreach($weights as $tag => $weight) {
                searchable::set_weight('testtype', $k, $tag, $weight);
            }

        }

        $results = searchable::results('testtype', ['one']);

        $this->assertEquals(array_keys($results), [1, 2, 3]);

        $results = searchable::results('testtype', ['two']);

        $this->assertEquals(array_keys($results), [2, 1, 3]);

        $results = searchable::results('testtype', ['three']);

        $this->assertEquals(count($results), 2);        

    }

}