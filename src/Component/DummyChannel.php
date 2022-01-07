<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

class DummyChannel extends Channel {
    public function __construct($id, $date)
    {
        parent::__construct($id);

        for($i=0; $i<12; $i++) {
            $time = strtotime($date)+$i*2*3600;
            $program = $this->addProgram($time, $time + 2 * 3600);
            $program->addTitle("Aucun programme");
        }
    }

}