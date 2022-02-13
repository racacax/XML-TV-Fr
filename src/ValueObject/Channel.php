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
