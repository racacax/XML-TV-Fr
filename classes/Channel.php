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
        $program = new Program($this->getFp(), $this, $start, $end);
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

    public function save() {
        fputs($this->getFp(), "<!-- $this->provider -->\n");
        foreach ($this->programs as $program) {
            $program->save();
        }
        if(empty($this->programs)) {
            @unlink($this->path);
        }
    }

    private function getFp()
    {
        if(!isset($this->fp))
            $this->fp = fopen($this->path, "a");
        return $this->fp;
    }
}