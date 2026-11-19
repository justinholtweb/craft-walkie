{{-- Walkie harness: /walkie/channels-blade?slug=…&site=… --}}
@php
    $site = request()->query('site');
    $entry = collect(\CraftCms\Cms\Entry\Elements\Entry::find()->slug(request()->query('slug'))->site('*')->status(null)->all())
        ->first(fn ($e) => $e->getSite()->handle === ($site ?: \CraftCms\Cms\Support\Facades\Sites::getCurrentSite()->handle));
@endphp
<p id="public">{{ $walkie->channel($entry) }}</p>
<p id="private">{{ $walkie->privateChannel($entry) }}</p>
<p id="presence">{{ $walkie->presenceChannel($entry) }}</p>
<p id="feed">{{ $walkie->sectionChannel('walkieNews', $site) }}</p>
<p id="privateFeed">{{ $walkie->sectionChannel('walkieNews', $site, true) }}</p>
<p id="secretFeed">{{ $walkie->sectionChannel('walkieSecret', $site) }}</p>
<p id="volume">{{ $walkie->volumeChannel('walkieFiles', $site) }}</p>
<p id="config">{{ $walkie->config($site) !== null ? json_encode($walkie->config($site)) : '' }}</p>
<div id="script">{{ $walkie->script($site) }}</div>
