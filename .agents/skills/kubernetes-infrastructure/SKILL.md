---
name: kubernetes-infrastructure
description: Maintain or troubleshoot Docker images, Kubernetes workloads, storage, networking, and deployment across this application and the kubedev infrastructure repository.
---

# Kubernetes Infrastructure

## Repository ownership

- This repository owns application images and their build inputs: `docker/`, `docker-compose.yaml`, `.dockerignore`,
  `deploy.sh`, `deploy-versions.txt`, `deploy-update-k8s-versions.sh`, and `deploy-local.sh`.
- `/Users/filip/Sites/kubedev` (normally `../kubedev` from the application root) owns the Kubernetes cluster
  definitions, manifests, and cluster maintenance scripts. Keep those definitions there instead of copying them into
  this repository. If the checkout is unavailable, continue application-side work and identify the missing
  infrastructure context.
- Before changing either checkout, inspect its Git status and applicable `AGENTS.md` instructions. Keep each
  repository's diff and validation results distinct.
- Start infrastructure navigation with `kubedev/README.md` and `kubedev/docs/my-portfolio-tracker.md`; read other
  component docs only as needed. Here, paths prefixed with `kubedev/` refer to that separate checkout.
- Desktop packaging remains separate; use [desktop-build](../desktop-build/SKILL.md) for it.

## Infrastructure map

Paths below are relative to the `kubedev` root. Verify current manifests before relying on resource names or mounts.

| Concern                         | Entry points                                                                                                                                            |
|---------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| Web workloads                   | `deployment/my-portfolio-tracker-php-deployment.yaml`, `deployment/my-portfolio-tracker-nginx-deployment.yaml`                                          |
| Service routing                 | `services/my-portfolio-tracker-php-service.yaml`, `services/my-portfolio-tracker-nginx-service.yaml`, `ingress/my-portfolio-tracker-nginx-ingress.yaml` |
| Scheduled work                  | `cronjobs/my-portfolio-tracker-cronjobs.yaml`                                                                                                           |
| RabbitMQ consumers              | `cronjobs/my-portfolio-tracker-queues-consumers.yaml`                                                                                                   |
| Persistent data                 | `volumes/my-portfolio-tracker-*.yaml` and the workloads that mount them                                                                                 |
| Application rollout             | `scripts/deploy-my-portfolio-tracker.sh`                                                                                                                |
| Cluster and supporting services | `README.md`, relevant files under `docs/`, and the corresponding `deployment/`, `services/`, `namespace/`, `volumes/`, and `scripts/` files             |

The current runtime uses MicroK8s. Application docs target namespace `default`; most application manifests omit
`metadata.namespace`. Nginx routes FastCGI to `php-service:9000`, matching `docker/nginx/conf.d/default.conf` in this
repository. Check selectors, labels, ports, and ingress backends together when changing routing.

## Deployment flow and side effects

Read the current scripts before executing a deployment; their names do not describe all side effects.

1. `deploy.sh` increments both lines in `deploy-versions.txt` (PHP first, Nginx second), runs `npm run build-docker`,
   builds the Docker Compose images, and pushes versioned PHP/Nginx images to `192.168.1.245:32000`. It changes versions
   before the build succeeds; inspect the resulting state before retrying a failed run.
2. `deploy-update-k8s-versions.sh` copies those versions into image references in the supplied directory. It traverses
   all files except `.git` and `.idea`, so inspect the full resulting diff. PHP tags must agree across the web
   container, init containers, scheduled jobs, and consumers; Nginx has its own version.
3. `deploy-local.sh` runs both steps, stages a fixed file list in `kubedev`, commits and pushes it, then connects to
   `filip@192.168.1.245` over SSH. The remote checkout is `/var/kubedev/kubedev`. Check the staged paths against the
   current checkout; the wrapper can retain references to removed manifests. It also sources a local NVM script; respect
   the session's shell-file restrictions when choosing an execution route.
4. The remote `scripts/deploy-my-portfolio-tracker.sh` applies the OAuth PVC, application CronJobs, and web deployments,
   deletes and recreates consumer CronJobs, then creates one-off Jobs for database migrations, queue declarations, and
   Doctrine proxy generation. It does not provision every Service, Ingress, or PVC; changes to those resources need
   their own targeted application.

A request to prepare or validate infrastructure changes does not by itself authorize publishing images, pushing Git
commits, SSH access, or changing the live cluster. Use authorization already given for the current task. For an
authorized rollout, establish the target cluster/context and namespace, review the exact diff and image versions, and
check referenced paths before invoking the relevant steps. If a step fails, inspect which actions completed before
retrying; do not blindly repeat the whole wrapper or migration Jobs.

## Changes across application and infrastructure

- The PHP image installs the application at `/var`; commands generally run from there. Preserve the build inputs
  expected by both Dockerfiles, including generated web assets. Keep desktop/iOS sources and artifacts outside web
  images through `.dockerignore`.
- Trace path and permission changes through all affected workloads. Current PHP workloads share log, Puppeteer, and
  Gemini storage at `/var/log`, `/var/puppeter/files`, and `/var/resources/gemini`; verify each workload's actual mounts
  rather than assuming they are identical.
- OAuth keys persist on `my-pt-oauth-pvc` at `/var/resources/oauth`, with a PHP init container setting ownership for
  `www-data`. Preserve existing keys and PVC data during ordinary deployments. For provisioning or path changes, read
  the [OAuth server provisioning reference](../oauth2-server/references/client-setup.md#server-provisioning).
- Consumer CronJobs currently use `concurrencyPolicy: Forbid` and run `notificationConsumer`, `jobRequestConsumer`, and
  `aiClientsConsumer`. Match schedule, command, suspension, overlap, and volume changes to the affected job.
  Use [rabbitmq-base](../rabbitmq-base/SKILL.md) when changing the PHP queue contract
  and [doctrine-migrations](../doctrine-migrations/SKILL.md) when changing database migrations.
- Follow the root secret-access restrictions. Do not inspect private NEON configuration, kubeconfig credentials,
  Kubernetes Secret payloads, or key contents to infer deployment settings; use public manifests, references, and
  non-secret metadata.

## Validation and runtime verification

- Follow the [application validation matrix](../../../AGENTS.md#validation-matrix) for files changed here, and any
  validation instructions in `kubedev` for changes there.
- Check changed shell scripts with `bash -n`; validate changed YAML documents and resource references locally with
  available tooling. Review image consistency, selectors, mounts, and deployment coverage relevant to the change.
  Running a deploy wrapper is not a validation command.
- For requested live diagnostics or a rollout, use the established access route and explicit namespace. Inspect workload
  status, events, and bounded relevant logs without exposing credentials. Verify rollout completion for affected
  deployments and completion of any Jobs started by the deployment; the script exiting successfully alone does not
  establish either.
- Report the changed files in each repository, checks actually performed, and whether a live rollout occurred. If
  cluster access is unavailable, state which runtime checks remain unverified.
