<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeUser(string $role): User
{
    return User::create([
        'name' => ucfirst($role).' User',
        'username' => $role.'_'.uniqid(),
        'email' => $role.'_'.uniqid().'@test.local',
        'password' => Hash::make('password123'),
        'role' => $role,
        'points' => 0,
    ]);
}

// ── Admin-only endpoints ─────────────────────────────────────────────────────
it('blocks a regular user from admin endpoints', function () {
    $this->actingAs(makeUser('user'))
        ->getJson('/api/admin/logs')->assertStatus(403);
});

it('blocks a regular user from the user list', function () {
    $this->actingAs(makeUser('user'))
        ->getJson('/api/users')->assertStatus(403);
});

it('allows an admin into admin endpoints', function () {
    $this->actingAs(makeUser('admin'))
        ->getJson('/api/admin/logs')->assertStatus(200);
});

// ── Employer: read moderation yes, user-management no ────────────────────────
it('lets an employer read the moderation dashboard', function () {
    $this->actingAs(makeUser('employer'))
        ->getJson('/api/employer/courses')->assertStatus(200);
});

it('forbids an employer from changing a user score', function () {
    $target = makeUser('user');
    $this->actingAs(makeUser('employer'))
        ->postJson("/api/admin/users/{$target->id}/score", ['points' => 999])
        ->assertStatus(403);

    expect($target->fresh()->points)->toBe(0);
});

it('lets an admin change a user score', function () {
    $target = makeUser('user');
    $this->actingAs(makeUser('admin'))
        ->postJson("/api/admin/users/{$target->id}/score", ['points' => 500])
        ->assertStatus(200);

    expect($target->fresh()->points)->toBe(500);
});

// ── Content-creation role gate ───────────────────────────────────────────────
it('forbids a regular user from creating examples', function () {
    $this->actingAs(makeUser('user'))
        ->postJson('/api/examples', [])->assertStatus(403);
});

// ── Soft-delete blocks re-login ──────────────────────────────────────────────
it('prevents a soft-deleted user from authenticating', function () {
    $user = makeUser('user');
    $user->delete();

    expect(App\Models\User::find($user->id))->toBeNull();
    expect(auth()->attempt(['email' => $user->email, 'password' => 'password123']))->toBeFalse();
});

it('lets an admin soft-delete a user', function () {
    $target = makeUser('user');
    $this->actingAs(makeUser('admin'))
        ->deleteJson("/api/admin/users/{$target->id}")->assertStatus(200);

    expect(App\Models\User::find($target->id))->toBeNull();
    expect(App\Models\User::withTrashed()->find($target->id)->trashed())->toBeTrue();
});

// ── Role-aware disable toggle ────────────────────────────────────────────────
function makeCourse(string $creatorId): App\Models\Course
{
    return App\Models\Course::create([
        'title' => 'C'.uniqid(), 'description' => 'd', 'category' => 'web',
        'level' => 'beginner', 'creator_id' => $creatorId, 'is_active' => true,
    ]);
}

it('lets a creator disable their own course', function () {
    $creator = makeUser('creator');
    $course = makeCourse($creator->id);
    $this->actingAs($creator)
        ->postJson("/api/courses/{$course->id}/toggle-active")->assertStatus(200);
    expect($course->fresh()->is_active)->toBeFalse();
});

it('forbids a creator from disabling someone elses course', function () {
    $owner = makeUser('creator');
    $other = makeUser('creator');
    $course = makeCourse($owner->id);
    $this->actingAs($other)
        ->postJson("/api/courses/{$course->id}/toggle-active")->assertStatus(403);
    expect($course->fresh()->is_active)->toBeTrue();
});

it('lets an employer disable any course', function () {
    $course = makeCourse(makeUser('creator')->id);
    $this->actingAs(makeUser('employer'))
        ->postJson("/api/courses/{$course->id}/toggle-active")->assertStatus(200);
    expect($course->fresh()->is_active)->toBeFalse();
});

// ── Lesson content management (owner / employer / admin) ─────────────────────
function makeLesson(App\Models\Course $course): App\Models\Lesson
{
    return $course->lessons()->create(['title' => 'L'.uniqid(), 'order_num' => 1]);
}

it('lets the course owner edit their lesson', function () {
    $owner = makeUser('creator');
    $lesson = makeLesson(makeCourse($owner->id));
    $this->actingAs($owner)
        ->putJson("/api/courses/{$lesson->course_id}/lessons/{$lesson->id}", ['title' => 'Updated'])
        ->assertStatus(200);
    expect($lesson->fresh()->title)->toBe('Updated');
});

it('forbids a creator from editing another creators lesson', function () {
    $lesson = makeLesson(makeCourse(makeUser('creator')->id));
    $this->actingAs(makeUser('creator'))
        ->putJson("/api/courses/{$lesson->course_id}/lessons/{$lesson->id}", ['title' => 'Hacked'])
        ->assertStatus(403);
});

it('lets an employer edit any lesson', function () {
    $lesson = makeLesson(makeCourse(makeUser('creator')->id));
    $this->actingAs(makeUser('employer'))
        ->putJson("/api/courses/{$lesson->course_id}/lessons/{$lesson->id}", ['title' => 'StaffEdit'])
        ->assertStatus(200);
});

// ── Comment moderation by the course owner ───────────────────────────────────
it('lets the course owner delete a comment on their lesson', function () {
    $owner = makeUser('creator');
    $lesson = makeLesson(makeCourse($owner->id));
    $comment = $lesson->comments()->create(['user_id' => makeUser('user')->id, 'content' => 'hi', 'course_id' => $lesson->course_id]);

    $this->actingAs($owner)
        ->deleteJson("/api/lessons/{$lesson->id}/comments/{$comment->id}")->assertStatus(200);
    expect(App\Models\LessonComment::find($comment->id))->toBeNull();
});

it('forbids an unrelated user from deleting a comment', function () {
    $lesson = makeLesson(makeCourse(makeUser('creator')->id));
    $comment = $lesson->comments()->create(['user_id' => makeUser('user')->id, 'content' => 'hi', 'course_id' => $lesson->course_id]);

    $this->actingAs(makeUser('user'))
        ->deleteJson("/api/lessons/{$lesson->id}/comments/{$comment->id}")->assertStatus(403);
});

// ── Credential fields never leak ─────────────────────────────────────────────
it('never exposes the password hash in admin user detail', function () {
    $target = makeUser('user');
    $response = $this->actingAs(makeUser('admin'))
        ->getJson("/api/admin/users/{$target->id}")->assertStatus(200);

    expect($response->json('user'))->not->toHaveKey('password');
});
