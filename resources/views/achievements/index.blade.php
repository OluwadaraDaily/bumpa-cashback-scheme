@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Your progress</p>
            <h1>Achievements and badges.</h1>
            <p>See what you have unlocked and what you can work towards next.</p>
        </div>
        <a class="button button-secondary" href="{{ route('shop') }}">Make a purchase</a>
    </section>

    <section class="progress-summary">
        <div>
            <p class="eyebrow">Current badge</p>
            <h2>{{ $progress['current_badge'] ?? 'No badge yet' }}</h2>
        </div>
        <div>
            <p class="eyebrow">Next badge</p>
            <h2>{{ $progress['next_badge'] ?? 'All badges unlocked' }}</h2>
            @if ($progress['next_badge'])
                <p class="muted">{{ $progress['remaining_to_unlock_next_badge'] }} achievement{{ $progress['remaining_to_unlock_next_badge'] === 1 ? '' : 's' }} remaining</p>
            @endif
        </div>
    </section>

    <div class="progress-layout">
        <section>
            <div class="subsection-heading">
                <div>
                    <p class="eyebrow">Milestones</p>
                    <h2>Achievements</h2>
                </div>
                <span class="count-pill">{{ $unlockedAchievements->count() }} / {{ $achievements->count() }} unlocked</span>
            </div>

            <div class="achievement-list">
                @foreach ($achievements as $achievement)
                    @php($unlocked = $unlockedAchievements->get($achievement->id))
                    <article class="achievement-card {{ $unlocked ? 'is-unlocked' : '' }}">
                        <div class="achievement-mark">{{ $unlocked ? '✓' : '·' }}</div>
                        <div class="achievement-content">
                            <div class="achievement-heading">
                                <h3>{{ $achievement->name }}</h3>
                                <span class="achievement-status">{{ $unlocked ? 'Unlocked' : 'Locked' }}</span>
                            </div>
                            <p>{{ $achievement->description }}</p>
                            @if ($unlocked)
                                <small>
                                    Unlocked {{ $unlocked->pivot->unlocked_at
                                        ? \Illuminate\Support\Carbon::parse($unlocked->pivot->unlocked_at)->format('M j, Y')
                                        : 'recently' }}
                                </small>
                            @else
                                <small>Keep shopping to reach this milestone.</small>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section>
            <div class="subsection-heading">
                <div>
                    <p class="eyebrow">Collections</p>
                    <h2>Badges</h2>
                </div>
                <span class="count-pill">{{ $unlockedBadges->count() }} / {{ $badges->count() }} unlocked</span>
            </div>

            <div class="badge-list">
                @foreach ($badges as $badge)
                    @php($unlocked = $unlockedBadges->get($badge->id))
                    <article class="badge-card {{ $unlocked ? 'is-unlocked' : '' }}">
                        <div class="badge-heading">
                            <div>
                                <p class="product-label">Badge</p>
                                <h3>{{ $badge->name }}</h3>
                            </div>
                            <span class="achievement-status">{{ $unlocked ? 'Unlocked' : 'Locked' }}</span>
                        </div>
                        <p>{{ $badge->description }}</p>
                        <div class="badge-requirements">
                            @foreach ($badge->achievements as $requiredAchievement)
                                <span class="{{ $unlockedAchievements->has($requiredAchievement->id) ? 'requirement-complete' : '' }}">
                                    {{ $unlockedAchievements->has($requiredAchievement->id) ? '✓' : '○' }}
                                    {{ $requiredAchievement->name }}
                                </span>
                            @endforeach
                        </div>
                        @if ($unlocked)
                            <small>
                                Unlocked {{ $unlocked->pivot->unlocked_at
                                    ? \Illuminate\Support\Carbon::parse($unlocked->pivot->unlocked_at)->format('M j, Y')
                                    : 'recently' }}
                            </small>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
