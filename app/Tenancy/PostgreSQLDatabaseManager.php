<?php

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager as BaseManager;

/**
 * Bases de datos de las empresas en PostgreSQL.
 *
 * - Borrar usa «WITH (FORCE)», que cierra las conexiones abiertas (la cola
 *   de correos, una ventana de pgAdmin…). Sin eso PostgreSQL se niega a
 *   borrar una base en uso y la empresa quedaba eliminada a medias.
 * - Saber si existe una base se pregunta con parámetros, sin pegar el
 *   nombre dentro del SQL.
 */
class PostgreSQLDatabaseManager extends BaseManager
{
    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $name = str_replace('"', '""', $tenant->database()->getName());

        return $this->database()->statement("DROP DATABASE IF EXISTS \"{$name}\" WITH (FORCE)");
    }

    public function databaseExists(string $name): bool
    {
        return $this->database()->select('SELECT 1 FROM pg_database WHERE datname = ?', [$name]) !== [];
    }
}
