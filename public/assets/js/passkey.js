// 注册 Passkey
async function registerPasskey(deviceName = '我的设备') {
    const res = await fetch('/admin/passkey/register-options');
    const options = await res.json();

    const credential = await navigator.credentials.create({
        publicKey: {
            challenge: Uint8Array.from(atob(options.challenge), c => c.charCodeAt(0)),
            rp: options.rp,
            user: {
                id: Uint8Array.from(options.user.id, c => c.charCodeAt(0)),
                name: options.user.name,
                displayName: options.user.displayName
            },
            pubKeyCredParams: options.pubKeyCredParams,
            timeout: options.timeout,
            attestation: options.attestation
        }
    });

    const data = {
        id: credential.id,
        response: {
            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
            attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject)))
        }
    };

    const saveRes = await fetch('/admin/passkey/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credential: JSON.stringify(data), device_name: deviceName })
    });

    return await saveRes.json();
}

// 使用 Passkey 登录
async function loginWithPasskey() {
    const res = await fetch('/admin/passkey/login-options');
    const options = await res.json();

    const assertion = await navigator.credentials.get({
        publicKey: {
            challenge: Uint8Array.from(atob(options.challenge), c => c.charCodeAt(0)),
            timeout: options.timeout,
            rpId: options.rpId
        }
    });

    const data = {
        id: assertion.id,
        response: {
            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(assertion.response.clientDataJSON))),
            authenticatorData: btoa(String.fromCharCode(...new Uint8Array(assertion.response.authenticatorData))),
            signature: btoa(String.fromCharCode(...new Uint8Array(assertion.response.signature)))
        }
    };

    const loginRes = await fetch('/admin/passkey/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credential: JSON.stringify(data) })
    });

    return await loginRes.json();
}