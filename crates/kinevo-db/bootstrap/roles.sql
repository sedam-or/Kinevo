\set ON_ERROR_STOP on
\pset tuples_only on

-- Supply these values with psql -v; this script is for operator-run bootstrap.
SELECT format(
    $bootstrap$
DO $kinevo$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_catalog.pg_roles WHERE rolname = 'kinevo_migrator') THEN
        EXECUTE %L;
    ELSE
        EXECUTE %L;
    END IF;
END
$kinevo$;
    $bootstrap$,
    format('ALTER ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', 'kinevo_migrator', :'migrator_password'),
    format('CREATE ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', 'kinevo_migrator', :'migrator_password')
)\gexec

SELECT format(
    $bootstrap$
DO $kinevo$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_catalog.pg_roles WHERE rolname = 'kinevo_app') THEN
        EXECUTE %L;
    ELSE
        EXECUTE %L;
    END IF;
END
$kinevo$;
    $bootstrap$,
    format('ALTER ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', 'kinevo_app', :'app_password'),
    format('CREATE ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', 'kinevo_app', :'app_password')
)\gexec

SELECT format(
    $bootstrap$
DO $kinevo$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_catalog.pg_roles WHERE rolname = 'kinevo_system') THEN
        EXECUTE %L;
    ELSE
        EXECUTE %L;
    END IF;
END
$kinevo$;
    $bootstrap$,
    format('ALTER ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', 'kinevo_system', :'system_password'),
    format('CREATE ROLE %I WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD %L', 'kinevo_system', :'system_password')
)\gexec
