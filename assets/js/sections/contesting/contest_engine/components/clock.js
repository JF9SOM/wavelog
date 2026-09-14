/**
 * ClockComponent - UTC/Local time display
 *
 * Shows the user's Wavelog-configured local time when the "local" time
 * display preference is enabled (window.ContestLoggerConfig.localtime),
 * otherwise shows plain UTC. Mirrors the offset trick used in qso.js's
 * getUTCTimeStamp(): apply the offset to the real timestamp, then read
 * it back out via the UTC getters.
 */
class ClockComponent {
    constructor(containerId = 'utc-time') {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`ClockComponent: Container #${containerId} not found`);
            return;
        }

        this.intervalId = null;
        this.init();
    }

    init() {
        this.updateTime();
        this.intervalId = setInterval(() => this.updateTime(), 1000);
        // console.info('ClockComponent: Initialized');
    }

    updateTime() {
        const localtimeCfg = window.ContestLoggerConfig?.localtime;
        let now = new Date();
        if (localtimeCfg && localtimeCfg.usage === '1') {
            now = new Date(now.getTime() + (localtimeCfg.offsetSeconds * 1000));
        }
        const hours = String(now.getUTCHours()).padStart(2, '0');
        const minutes = String(now.getUTCMinutes()).padStart(2, '0');
        const seconds = String(now.getUTCSeconds()).padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds}`;

        if (this.container) {
            this.container.innerHTML = timeString;
        }
    }

    destroy() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }
}

// Self-register when app is ready
window.addEventListener('contestAppReady', () => {
    const clockComponent = new ClockComponent('utc-time');

    // Expose to contestApp
    if (window.contestApp) {
        window.contestApp.clockComponent = clockComponent;
    }
});

// Register globally for debugging
window.ClockComponent = ClockComponent;