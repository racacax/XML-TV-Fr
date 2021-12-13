<?php
class Channel {
    private $programs;
    private $fp;
    private $id;
    private $path;

    /**
     * Channel constructor.
     * @param $id
     * @param $date
     */
    public function __construct($id, $date)
    {
        $this->id = $id;
        $this->programs = [];

        $path = Utils::generateFilePath($id,$date);
        if(file_exists($path))
            unlink($path);
        $this->fp = fopen($path, "a");
        $this->path = $path;
    }

    /**
     * @param $start
     * @param $end
     * @return Program
     */
    public function addProgram($start, $end) {
        $program = new Program($this->fp, $this, $start, $end);
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
        foreach ($this->programs as $program) {
            $program->save();
        }
        if(empty($this->programs)) {
            @unlink($this->path);
        }
    }
}