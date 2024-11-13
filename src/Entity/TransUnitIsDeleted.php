<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Lexik\Bundle\TranslationBundle\Entity\TransUnit;
use WhiteDigital\EntityResourceMapper\Entity\Traits\Id;

#[ORM\Entity]
#[ORM\Table(name: 'lexik_trans_unit_is_deleted')]
#[ORM\Index(fields: ['isDeleted'])]
#[ORM\Index(fields: ['transUnit', 'isDeleted'])]
class TransUnitIsDeleted
{
    use Id;

    #[ORM\ManyToOne]
    private ?TransUnit $transUnit = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function getTransUnit(): ?TransUnit
    {
        return $this->transUnit;
    }

    public function setTransUnit(?TransUnit $transUnit): self
    {
        $this->transUnit = $transUnit;

        return $this;
    }

    public function getisDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }
}
