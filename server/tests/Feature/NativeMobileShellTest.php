<?php

namespace Tests\Feature;

use App\NativeComponents\CanvasScreen;
use App\NativeComponents\CaptureScreen;
use App\NativeComponents\GoalScreen;
use App\NativeComponents\MoreScreen;
use App\NativeComponents\NotesScreen;
use App\NativeComponents\NotificationsScreen;
use App\NativeComponents\ReviewScreen;
use App\NativeComponents\TaskDetailScreen;
use App\NativeComponents\TasksScreen;
use App\NativeComponents\TodayScreen;
use App\NativeComponents\WorkspacesScreen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Native\Mobile\Edge\NativeComponent;
use Tests\TestCase;

/**
 * P27 — the in-repo NativePHP Android shell must stay registered and its
 * screen views valid. Rendering happens inside the embedded Android runtime
 * (NATIVEPHP_RUNNING); these tests lock the server-side contract so the
 * bundle cannot silently drop a screen.
 */
class NativeMobileShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_native_screen_routes_are_registered(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->values()
            ->all();

        foreach (['/', 'tasks', 'tasks/{taskId}', 'capture', 'goals', 'goals/{goalId}', 'goals/{goalId}/breakdown', 'review', 'notifications', 'canvases', 'canvases/{canvasId}', 'notes', 'notes/{noteId}', 'workspaces', 'more'] as $uri) {
            $this->assertContains($uri, $uris, "Native route /{$uri} must stay registered.");
        }
    }

    public function test_every_native_component_maps_to_its_view_file(): void
    {
        $cases = [
            TodayScreen::class => 'today',
            TasksScreen::class => 'tasks',
            TaskDetailScreen::class => 'task-detail',
            CaptureScreen::class => 'capture',
            GoalScreen::class => 'goals',
            ReviewScreen::class => 'review',
            NotificationsScreen::class => 'notifications',
            CanvasScreen::class => 'canvases',
            NotesScreen::class => 'notes',
            WorkspacesScreen::class => 'workspaces',
            MoreScreen::class => 'more',
        ];

        foreach ($cases as $class => $view) {
            $component = new $class;
            $this->assertInstanceOf(NativeComponent::class, $component, "{$class} must be a native component.");
            $this->assertFileExists(resource_path('views/native/'.$view.'.blade.php'), "{$view} view must exist.");
            $this->assertSame($view, $this->viewNameFromSource($class), "{$class}::render must target the {$view} view.");
        }
    }

    private function viewNameFromSource(string $class): ?string
    {
        $reflection = new \ReflectionClass($class);
        $method = $reflection->getMethod('render');
        $source = implode("\n", array_slice(file($reflection->getFileName()), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $resolved = preg_match("/view\\((['\"])([a-z-]+)\\1/", $source, $m);
        if ($resolved !== 1) {
            return null;
        }

        return $m[2];
    }
}
