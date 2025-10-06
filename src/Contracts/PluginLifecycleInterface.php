<?php

namespace SoysalTan\LaravelPluginSystem\Contracts;

interface PluginLifecycleInterface
{
    public function onInstall(): void;

    public function onUninstall(): void;

    public function onActivate(): void;

    public function onDeactivate(): void;

    public function onBoot(): void;

    public function onRegister(): void;
}
