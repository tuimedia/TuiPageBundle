<?php

namespace Tui\PageBundle\Entity;

interface PageDataInterface
{
    public function getPageRef(): ?string;

    public function setPageRef(string $pageRef): self;

    public function getDefaultLanguage(): ?string;

    public function setDefaultLanguage(string $defaultLanguage): self;

    /** @return string[] */
    public function getAvailableLanguages(): array;

    public function setAvailableLanguages(array $availableLanguages): self;

    public function getCreated(): ?\DateTimeImmutable;

    public function setCreated(\DateTimeImmutable $created): self;

    public function getRevision(): ?string;

    public function setRevision(?string $revision): self;

    public function getPreviousRevision(): ?string;

    public function setPreviousRevision(?string $previousRevision): self;

    public function getContent(): array;

    public function setContent(array $content): self;

    public function getMetadata(): array;

    public function setMetadata(array $metadata): self;
}
