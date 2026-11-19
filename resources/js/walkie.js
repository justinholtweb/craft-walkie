/**
 * Walkie client: a thin layer over Laravel Echo for Walkie's channels.
 *
 *   import { createWalkie } from '/vendor/justinholtweb/craft-walkie/walkie.js';
 *   const walkie = createWalkie(echoOrEchoClass, config); // config: {{ walkie.config()|json_encode }}
 *
 *   walkie.on({ id: 12 }, 'saved', (msg) => refetch(msg.id));          // public entry channel
 *   walkie.on({ id: 12, private: true }, '*', (msg) => ...);           // everything, needs permission
 *   walkie.onSection('news', 'saved', (msg) => ...);                    // public section feed
 *   walkie.here({ id: 12 }, { here, joining, leaving });                // presence (signed in)
 *
 * Or let `{{ walkie.script() }}` start it as `window.walkie` (listen for `walkie:ready`).
 * Messages carry ids and statuses only: refetch what you show.
 */

const PRIVATE_EVENTS = ['created', 'saved', 'restored', 'status', 'deleted'];
const PUBLIC_EVENTS = ['saved', 'removed'];

export function createWalkie(echo, config = {}) {
  const site = config.site;
  const prefix = config.prefix || 'walkie';
  const subscribed = new Map();

  if (typeof echo === 'function') {
    const options = Object.assign({}, config.echo || {}, {
      authEndpoint: config.authEndpoint,
      auth: { headers: config.csrfToken ? { 'X-CSRF-TOKEN': config.csrfToken } : {} },
    });
    if (typeof window !== 'undefined' && window.Pusher && !options.Pusher) {
      options.Pusher = window.Pusher;
    }
    echo = new echo(options);
  }

  if (!echo || typeof echo.channel !== 'function') {
    throw new Error('Walkie needs a Laravel Echo instance (or the Echo class and window.Pusher).');
  }

  const name = (target, kind) => {
    if (typeof target === 'string') return target;
    const t = target || {};
    if (kind === 'section') return `${prefix}.${t.site || site}.entries.${t.section}`;
    if (kind === 'volume') return `${prefix}.${t.site || site}.assets.${t.volume}`;
    return `${prefix}.${t.site || site}.${t.type || 'entry'}.${t.id}`;
  };

  const channelFor = (channel, isPrivate) => {
    const key = (isPrivate ? 'private-' : '') + channel;
    if (!subscribed.has(key)) {
      subscribed.set(key, { channel: isPrivate ? echo.private(channel) : echo.channel(channel), handlers: 0 });
    }
    return subscribed.get(key);
  };

  const listen = (channel, isPrivate, event, callback) => {
    const known = isPrivate ? PRIVATE_EVENTS : PUBLIC_EVENTS;
    let events;
    let handler = callback;

    if (event === '*') {
      events = known;
    } else if (event === 'status' && isPrivate) {
      // Scheduled transitions arrive as `status`; saves that changed the status as `saved`/`created`.
      events = ['status', 'saved', 'created', 'restored'];
      handler = (msg) => {
        if (msg.event === 'status' || msg.status !== msg.previousStatus) callback(msg);
      };
    } else {
      events = [event];
    }

    const entry = channelFor(channel, isPrivate);
    const bound = events.map((e) => {
      const fn = (msg) => handler(msg);
      entry.channel.listen('.' + e, fn);
      return [e, fn];
    });
    entry.handlers += 1;

    return () => {
      bound.forEach(([e, fn]) => entry.channel.stopListening('.' + e, fn));
      entry.handlers -= 1;
      if (entry.handlers <= 0) {
        const key = (isPrivate ? 'private-' : '') + channel;
        echo.leaveChannel(key);
        subscribed.delete(key);
      }
    };
  };

  return {
    echo,
    config,

    /** Listen on an element's channel. `target`: a channel name, or {id, type, site, private}. Returns an unsubscribe function. */
    on(target, event, callback, options = {}) {
      const isPrivate = Boolean(options.private ?? (typeof target === 'object' && target.private));
      return listen(name(target, 'element'), isPrivate, event, callback);
    },

    /** Listen on a section's feed (public unless `{private: true}`). */
    onSection(section, event, callback, options = {}) {
      const target = typeof section === 'string' && !section.includes('.') ? { section } : section;
      return listen(name(target, 'section'), Boolean(options.private), event, callback);
    },

    /** Listen on a volume's feed (always private). */
    onVolume(volume, event, callback) {
      const target = typeof volume === 'string' && !volume.includes('.') ? { volume } : volume;
      return listen(name(target, 'volume'), true, event, callback);
    },

    /** Join an element's presence channel: callbacks get [{id, name}, …] / {id, name}. */
    here(target, { here, joining, leaving } = {}) {
      const channel = name(target, 'element');
      const presence = echo.join(channel);
      if (here) presence.here(here);
      if (joining) presence.joining(joining);
      if (leaving) presence.leaving(leaving);
      return () => echo.leaveChannel('presence-' + channel);
    },

    channelName: name,
  };
}

if (typeof window !== 'undefined' && window.walkieConfig && !window.walkie) {
  const start = () => {
    if (window.walkie || !window.Echo) return Boolean(window.walkie);
    try {
      window.walkie = createWalkie(window.Echo, window.walkieConfig);
      window.dispatchEvent(new CustomEvent('walkie:ready', { detail: window.walkie }));
    } catch (e) {
      console.warn('[walkie]', e.message);
    }
    return true;
  };
  if (!start()) {
    let tries = 0;
    const timer = setInterval(() => {
      if (start() || ++tries > 50) clearInterval(timer);
    }, 100);
  }
}
