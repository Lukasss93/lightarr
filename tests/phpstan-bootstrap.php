<?php

use Livewire\Features\SupportTesting\SupportTesting;

app()->detectEnvironment(fn (): string => 'testing');

SupportTesting::provide();
