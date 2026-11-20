<?php

namespace App\Tenancy;

use Illuminate\Database\QueryException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager as BaseManager;

/**
 * Bases de datos de las empresas en PostgreSQL.
 *
 * - Antes de borrar una base cierra las conexiones que la app tenga
 *   abiertas a ella (la cola de correos, otra petición). Sin eso PostgreSQL
 *   se niega a borrar una base en uso y la empresa quedaba eliminada a
 *   medias.
 *   No usa «DROP … WITH (FORCE)»: exige permisos especiales para cerrar
 *   procesos de otros usuarios de PostgreSQL, y falla al azar cuando el
 *   mantenimiento automático (autovacuum) está trabajando en esa base. El
 *   DROP normal ya espera a que el mantenimiento la suelte.
 * - Saber si existe una base se pregunta con parámetros, sin pegar el
 *   nombre dentro del SQL.
 */
class PostgreSQLDatabaseManager extends BaseManager
{
    /** «La base está siendo usada por otros usuarios». */
    private const OBJECT_IN_USE = '55006';

    private const ATTEMPTS = 5;

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $name = $tenant->database()->getName();
        $quoted = '"'.str_replace('"', '""', $name).'"';

        for ($attempt = 1; ; $attempt++) {
            // Solo las conexiones del mismo usuario de la app. Si queda una
            // de otro usuario (p. ej. pgAdmin como «postgres»), el DROP falla
            // con un error claro y la empresa no se borra a medias.
            $this->database()->select(
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity
                 WHERE datname = ? AND pid <> pg_backend_pid() AND usename = current_user',
                [$name],
            );

            try {
                return $this->database()->statement("DROP DATABASE IF EXISTS {$quoted}");
            } catch (QueryException $e) {
                if ($attempt >= self::ATTEMPTS || (string) $e->getCode() !== self::OBJECT_IN_USE) {
                    throw $e;
                }

                usleep(500_000);
            }
        }
    }

    public function databaseExists(string $name): bool
    {
        return $this->database()->select('SELECT 1 FROM pg_database WHERE datname = ?', [$name]) !== [];
    }
}
