// Silent single sign-on for the Microsoft Teams tab. Runs inside the framed
// `/teams/auth` page: initialises the Teams SDK, obtains an Entra token for the signed-in
// Teams user, and posts it to the backend, which validates it and establishes a Mosi
// session. On success the tab navigates into the app; otherwise it shows a fallback.
import { app, authentication } from '@microsoft/teams-js';

const mount = document.getElementById('teams-auth');
const status = document.getElementById('status');

function fail() {
    if (status && mount) {
        status.textContent = mount.dataset.failMessage;
    }
}

(async () => {
    if (!mount) {
        return;
    }

    try {
        await app.initialize();

        const token = await authentication.getAuthToken();

        const response = await fetch(mount.dataset.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': mount.dataset.csrf,
            },
            body: JSON.stringify({ token }),
        });

        if (response.ok) {
            window.location = mount.dataset.dashboardUrl;
        } else {
            fail();
        }
    } catch (error) {
        fail();
    }
})();
