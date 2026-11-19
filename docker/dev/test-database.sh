# Sourced by the MariaDB entrypoint when initializing the development volume.
case "$MPT_DEV_TEST_DB_PASSWORD" in
	''|*[!0-9a-f]*) echo 'Expected a generated hexadecimal test database password.' >&2; exit 1 ;;
esac

docker_process_sql <<SQL
CREATE DATABASE \`my-portfolio-app-tracker-tests\`;
CREATE USER 'portfolio_test'@'%' IDENTIFIED BY '${MPT_DEV_TEST_DB_PASSWORD}';
GRANT ALL ON \`my-portfolio-app-tracker-tests\`.* TO 'portfolio_test'@'%';
SQL
