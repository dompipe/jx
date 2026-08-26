<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx/Flow.php';

use jx\Flow;
use jx\Jx;
use jx\Task;

// A Bag sentence: put this, into this, at this place.
$state = Jx::bag(256);
Flow::put('hello jx', $state, 'message');
$message = Flow::take($state, 'message');

// A geometry sentence: line, from here, to there, like this.
$line = Flow::line(
    'sweep-line',
    Flow::from(0, 40),
    Flow::to(160, 40),
    Flow::like(Control::pong()),
);

// A longer geometry sentence becomes a readable path.
$curve = Flow::curve(
    'motion-curve',
    Flow::from(0, 80),
    Flow::through(40, 10),
    Flow::through(120, 130),
    Flow::to(180, 80),
    Flow::like(['smooth' => 0.82]),
);

// A Book becomes a paragraph: one subject, several clauses.
$book = Flow::book('learning-room')
    ->withBag('state', $state)
    ->withPage('home', function (Task $task): int {
        $task->push('opened', true);
        return $task->id();
    })
    ->done();

$pageResult = $book->page('home')->run();

echo json_encode([
    'message' => $message,
    'line' => $line,
    'curve' => $curve,
    'book' => $book->name(),
    'pageResult' => $pageResult,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
