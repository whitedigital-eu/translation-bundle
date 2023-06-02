<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Entity\Traits\Id;
use WhiteDigital\Translation\ApiResource\TranslationResource;
use WhiteDigital\Translation\Repository\TranslationRepository;

#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[Mapping(TranslationResource::class)]
class Translation extends BaseEntity
{
    use Id;

    #[ORM\Column]
    protected ?string $domain = null;

    #[ORM\Column]
    protected ?string $locale = null;

    #[ORM\Column]
    protected ?string $key = null;

    #[ORM\Column(type: Types::TEXT)]
    protected ?string $translation = null;

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }

    public function setTranslation(?string $translation): self
    {
        $this->translation = $translation;

        return $this;
    }
}
