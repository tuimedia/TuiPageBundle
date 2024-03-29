<?php

namespace Tui\PageBundle\Entity;

interface PageInterface
{
    public function getId(): ?string;

    public function getSlug(): ?string;

    public function setSlug(string $slug): PageInterface;

    public function getState(): ?string;

    public function setState(string $state): PageInterface;

    public function getPageData(): PageDataInterface;

    public function setPageData(PageDataInterface $pageData): PageInterface;
}
