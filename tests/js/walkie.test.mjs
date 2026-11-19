// node --test tests/js — the client against a fake Laravel Echo.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createWalkie } from '../../resources/js/walkie.js';

function fakeEcho() {
  const channels = new Map();
  const log = [];
  const make = (full) => {
    if (!channels.has(full)) {
      const listeners = new Map();
      channels.set(full, {
        listeners,
        listen(e, fn) { (listeners.get(e) || listeners.set(e, []).get(e)).push(fn); return this; },
        stopListening(e, fn) { listeners.set(e, (listeners.get(e) || []).filter((f) => f !== fn)); return this; },
        here(fn) { this.hereFn = fn; return this; }, joining() { return this; }, leaving() { return this; },
      });
    }
    return channels.get(full);
  };
  return {
    log, channels,
    channel: (n) => make(n), private: (n) => make('private-' + n), join: (n) => make('presence-' + n),
    leaveChannel: (n) => { log.push('leave ' + n); channels.delete(n); },
    emit(full, event, msg) { (channels.get(full)?.listeners.get('.' + event) || []).forEach((fn) => fn(msg)); },
  };
}

const config = { site: 'default', prefix: 'walkie' };

test('listens on an entry’s public channel with Echo’s dot prefix', () => {
  const echo = fakeEcho();
  const got = [];
  createWalkie(echo, config).on({ id: 12 }, 'saved', (m) => got.push(m.id));
  echo.emit('walkie.default.entry.12', 'saved', { id: 12 });
  assert.deepEqual(got, [12]);
});

test('private channels and section feeds', () => {
  const echo = fakeEcho();
  const got = [];
  const w = createWalkie(echo, config);
  w.on({ id: 5, private: true }, '*', (m) => got.push(m.event));
  w.onSection('news', 'removed', (m) => got.push('feed ' + m.event));
  w.onVolume('images', 'deleted', (m) => got.push('vol ' + m.event));
  echo.emit('private-walkie.default.entry.5', 'created', { event: 'created' });
  echo.emit('private-walkie.default.entry.5', 'deleted', { event: 'deleted' });
  echo.emit('walkie.default.entries.news', 'removed', { event: 'removed' });
  echo.emit('private-walkie.default.assets.images', 'deleted', { event: 'deleted' });
  assert.deepEqual(got, ['created', 'deleted', 'feed removed', 'vol deleted']);
});

test('status listens for scheduled changes and saves that changed the status', () => {
  const echo = fakeEcho();
  const got = [];
  createWalkie(echo, config).on({ id: 1, private: true }, 'status', (m) => got.push(m.status));
  echo.emit('private-walkie.default.entry.1', 'saved', { status: 'live', previousStatus: 'live' });
  echo.emit('private-walkie.default.entry.1', 'saved', { status: 'disabled', previousStatus: 'live' });
  echo.emit('private-walkie.default.entry.1', 'status', { event: 'status', status: 'expired', previousStatus: 'live' });
  assert.deepEqual(got, ['disabled', 'expired']);
});

test('unsubscribing leaves the channel once the last listener goes', () => {
  const echo = fakeEcho();
  const w = createWalkie(echo, config);
  const a = w.on({ id: 2 }, 'saved', () => {});
  const b = w.on({ id: 2 }, 'removed', () => {});
  a();
  assert.deepEqual(echo.log, []);
  b();
  assert.deepEqual(echo.log, ['leave walkie.default.entry.2']);
});

test('builds Echo from the class with the auth endpoint and CSRF token', () => {
  let options;
  class FakeEcho { constructor(o) { options = o; Object.assign(this, fakeEcho()); } }
  createWalkie(FakeEcho, { ...config, authEndpoint: '/broadcasting/auth', csrfToken: 't0k', echo: { broadcaster: 'reverb', key: 'k' } });
  assert.equal(options.broadcaster, 'reverb');
  assert.equal(options.authEndpoint, '/broadcasting/auth');
  assert.equal(options.auth.headers['X-CSRF-TOKEN'], 't0k');
});

test('other sites and explicit channel names', () => {
  const w = createWalkie(fakeEcho(), config);
  assert.equal(w.channelName({ id: 3, site: 'fr' }), 'walkie.fr.entry.3');
  assert.equal(w.channelName('walkie.fr.entries.news'), 'walkie.fr.entries.news');
});

test('refuses to start without Echo', () => {
  assert.throws(() => createWalkie(null, config), /Laravel Echo/);
});
