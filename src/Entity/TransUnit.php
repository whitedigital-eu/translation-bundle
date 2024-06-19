<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Entity;

use Doctrine\ORM\Mapping as ORM;

class TransUnit extends \Lexik\Bundle\TranslationBundle\Entity\TransUnit
{
    #[ORM\Column(nullable: true, options: ['default' => false])]
    public bool $isDeleted = false;

    public function getIsDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }
}
