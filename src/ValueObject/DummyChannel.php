<?php
declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

class DummyChannel extends Channel {
    public function __construct($id, string $icon, string $name, $date)
    {
        parent::__construct($id, $icon, $name);

        for($i=0; $i<12; $i++) {
            $time = strtotime($date)+$i*2*3600;
            $program = $this->addProgram($time, $time + 2 * 3600);
            $program->addTitle("Aucun programme");
        }
    }

}