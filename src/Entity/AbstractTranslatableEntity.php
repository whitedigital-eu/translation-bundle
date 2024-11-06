<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Entity\Traits\Id;

#[ORM\MappedSuperclass]
abstract class AbstractTranslatableEntity extends BaseEntity implements Translatable
{
    use Id;

    #[Gedmo\Locale]
    protected ?string $locale = null;

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }
}
