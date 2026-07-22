<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Admin;

use Automattic\AiEvals\Kernel;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Storage\HistoryStore;
use Automattic\AiEvals\Storage\RunSessionStore;

final class RestController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'wp-ai-evals/v1',
            '/run',
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'run'],
                'permission_callback' => [$this, 'canRun'],
                'args' => [
                    'suites' => $this->listArgument(),
                    'tags' => $this->listArgument(),
                    'cases' => $this->listArgument(),
                    'repetitions' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                        'maximum' => 10,
                    ],
                ],
            ]
        );

        register_rest_route(
            'wp-ai-evals/v1',
            '/run-sessions',
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'startSession'],
                'permission_callback' => [$this, 'canRun'],
                'args' => $this->selectionArguments(),
            ]
        );

        register_rest_route(
            'wp-ai-evals/v1',
            '/run-sessions/(?P<run_id>[a-zA-Z0-9._-]+)',
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getSession'],
                'permission_callback' => [$this, 'canRun'],
            ]
        );

        register_rest_route(
            'wp-ai-evals/v1',
            '/run-sessions/(?P<run_id>[a-zA-Z0-9._-]+)/next',
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'advanceSession'],
                'permission_callback' => [$this, 'canRun'],
            ]
        );

        register_rest_route(
            'wp-ai-evals/v1',
            '/runs/(?P<run_id>[a-zA-Z0-9._-]+)',
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getRun'],
                'permission_callback' => [$this, 'canRun'],
            ]
        );
    }

    public function canRun(): bool
    {
        return current_user_can($this->capability());
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->kernel->initialize();
        $report = (new Runner())->run(
            $this->kernel->getRegistry(),
            $this->selection($request)
        );

        $history = new HistoryStore();
        $history->save($report);
        $normalized = json_decode((string) wp_json_encode($report), true);

        return rest_ensure_response([
            'report' => is_array($normalized) ? $normalized : [],
            'history' => $history->all(),
        ]);
    }

    public function startSession(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->kernel->initialize();
        $session = (new RunSessionStore())->start(
            $this->kernel->getRegistry(),
            $this->selection($request),
            new Runner()
        );

        return rest_ensure_response(['session' => $session]);
    }

    public function getSession(\WP_REST_Request $request): \WP_REST_Response
    {
        $session = (new RunSessionStore())->status((string) $request->get_param('run_id'));
        if (null === $session) {
            return new \WP_REST_Response(['message' => __('The live evaluation run was not found or expired.', 'wp-ai-evals')], 404);
        }

        return rest_ensure_response(['session' => $session]);
    }

    public function advanceSession(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->kernel->initialize();

        try {
            $advanced = (new RunSessionStore())->advance(
                (string) $request->get_param('run_id'),
                $this->kernel->getRegistry(),
                new Runner()
            );
        } catch (\Throwable $error) {
            return new \WP_REST_Response(['message' => $error->getMessage()], 404);
        }

        return rest_ensure_response([
            'session' => $advanced['session'],
            'report' => $this->normalize($advanced['report']),
            'history' => null !== $advanced['report'] ? (new HistoryStore())->all() : null,
        ]);
    }

    public function getRun(\WP_REST_Request $request): \WP_REST_Response
    {
        $report = (new HistoryStore())->find((string) $request->get_param('run_id'));
        if (null === $report) {
            return new \WP_REST_Response([
                'message' => __('Full details are not available for this run. Older summary-only runs cannot be expanded.', 'wp-ai-evals'),
            ], 404);
        }

        return rest_ensure_response(['report' => $report]);
    }

    /** @return array<string, mixed> */
    private function listArgument(): array
    {
        return [
            'type' => 'array',
            'default' => [],
            'items' => ['type' => 'string'],
        ];
    }

    /** @return array<string, mixed> */
    private function selectionArguments(): array
    {
        return [
            'suites' => $this->listArgument(),
            'tags' => $this->listArgument(),
            'cases' => $this->listArgument(),
            'repetitions' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'maximum' => 10,
            ],
        ];
    }

    private function selection(\WP_REST_Request $request): Selection
    {
        return new Selection(
            $this->sanitizeList($request->get_param('suites')),
            $this->sanitizeList($request->get_param('cases')),
            $this->sanitizeList($request->get_param('tags')),
            max(1, min(10, (int) $request->get_param('repetitions')))
        );
    }

    /** @param mixed $value @return array<string, mixed>|null */
    private function normalize($value): ?array
    {
        if (null === $value) {
            return null;
        }

        $normalized = json_decode((string) wp_json_encode($value), true);

        return is_array($normalized) ? $normalized : [];
    }

    /** @param mixed $value @return list<string> */
    private function sanitizeList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($item): string => sanitize_text_field((string) $item),
            $value
        ))));
    }

    private function capability(): string
    {
        return (string) apply_filters('wp_ai_evals_capability', 'manage_options');
    }
}
