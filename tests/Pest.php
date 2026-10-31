<?php

use Tests\TestCase;

/*
 * I test a classe esistenti (PHPUnit) continuano a funzionare così come sono.
 * I test Pest a funzione nelle cartelle Feature usano l'applicazione Laravel;
 * i test Unit e Arch del motore restano puri (senza framework).
 */
pest()->extend(TestCase::class)->in('Feature', 'Engine/Feature');
