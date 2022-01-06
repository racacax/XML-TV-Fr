<?php
class Channel {
    private $programs;
    private $fp;
    private $id;
    private $path;
    private $provider;

    /**
     * Channel constructor.
     * @param $id
     * @param $date
     * @param $provider
     */
    public function __construct($id, $date, $provider)
    {
        $this->id = $id;
        $this->programs = [];
        $this->provider = $provider;

        $path = generateFilePath($id,$date);
        if(file_exists($path))
            unlink($path);
        $this->path = $path;
    }

    /**
     * @param $start
     * @param $end
     * @return Program
     */
    public function addProgram($start, $end) {
        $program = new Program($this, $start, $end);
        $this->programs[] = $program;
        return $program;
    }

    /**
     * @return array
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



    public function toString() {
        $str = '';
        foreach ($this->programs as $program) {
            $str.= $program->toString();
        }
        return $str;
    }

    public function save($minimum=1) {
        fputs($this->getFp(), "<!-- $this->provider -->\n");
        foreach ($this->programs as $program) {
            $program->save();
        }
        fclose($this->getFp());
        if(count($this->programs) < $minimum) {
            @unlink($this->path);
            return false;
        }
        return true;
    }

    public function getFp()
    {
        if(!isset($this->fp))
            $this->fp = fopen($this->path, "a");
        return $this->fp;
    }
    public function getProgramCount() {
        return count($this->getPrograms());
    }
    public function popLastProgram() {
        return array_pop($this->programs);
    }
}