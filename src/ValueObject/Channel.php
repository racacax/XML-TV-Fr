<?php

declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

class Channel
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string|null
     */
    private $icon;
    /**
     * @var string|null
     */
    private $name;
    /**
     * @var Program[]
     */
    private $programs;

    /**
     * Channel constructor.
     */
    public function __construct(string $id, ?string $icon, ?string $name)
    {
        $this->id = $id;
        $this->icon = $icon;
        $this->name = $name;
        $this->programs = [];
    }

    public function getStartTimes(): array
    {
        $startTimes = [];
        foreach ($this->programs as $program) {
            $startTimes[] = strtotime($program->getStartFormatted());
        }

        return $startTimes;
    }
    public function getEndTimes(): array
    {
        $endTimes = [];
        foreach ($this->programs as $program) {
            $endTimes[] = strtotime($program->getEndFormatted());
        }

        return $endTimes;
    }
    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function addProgram(Program $program): void
    {
        $this->programs[] = $program;
    }


    public function orderProgram(): void
    {
        usort(
            $this->programs,
            function (Program $program1, Program $program2) {
                return $program1->getStart() <=> $program2->getStart();
            }
        );
    }

    public function getLatestStartDate()
    {
        $startTimes = $this->getStartTimes();

        return max($startTimes);
    }

    /**
     * @return Program[]
     */
    public function getPrograms(): array
    {
        return $this->programs;
    }

    public function getProgramCount()
    {
        return count($this->getPrograms());
    }

    public function popLastProgram()
    {
        return array_pop($this->programs);
    }
}
