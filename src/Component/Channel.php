<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Channel {
    /**
     * @var Program[]
     */
    private $programs;
    private $fp;
    private $id;

    /**
     * Channel constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->programs = [];
    }

    /**
     * @param $start
     * @param $end
     * @return Program
     */
    public function addProgram($start, $end): Program
    {
        $program = new Program($start, $end);
        $this->programs[] = $program;
        return $program;
    }

    /**
     * @return Program[]
     */
    public function getPrograms(): array
    {
        return $this->programs;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    public function getProgramCount() {
        return count($this->getPrograms());
    }

    public function popLastProgram() {
        return array_pop($this->programs);
    }
}