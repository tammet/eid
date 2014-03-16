// vim: set ts=4 sw=4 et:
/**
 * Selects and loads the best plugin for the user's environment.
 */
function loadPlugin() {
    try {
        loadSigningPlugin();
    } catch (e) {
        show_exception(e);
        return false;
    }
}

/**
 * Reads the certificate's data and inserts it into the given form.
 */
function read_certificate(form) {
    try {
        var cert = new IdCardPluginHandler().getCertificate();
        form.elements["cert_hex"].value = cert.cert;
        form.elements["cert_id"].value = cert.id;
    } catch(e) {
        show_exception(e);
        return false;
    }
}

/**
 * Signs the hash and inserts the data into the given form.
 */
function sign_hash(cert_id, hash, form) {
    try {
        var signature = new IdCardPluginHandler().sign(cert_id, hash);
        form.elements["signature"].value = signature;
    } catch(e) {
        show_exception(e);
        return false;
    }
}

/**
 * Displays the error in a popup.
 */
function show_exception(e) {
    alert("Viga" + (e instanceof IdCardException ? ' ' + e.returnCode : "")
            + ": " + e.message);
}

/**
 * A way of handling listeners that supports IE < 9.
 */
function addListener(el, ev, listener) {
    if (el.addEventListener) {  
          el.addEventListener(ev, listener, false);   
    } else if (el.attachEvent)  {  
          el.attachEvent("on" + ev, listener);  
    }
}

/* If the window has finished loading, load the plugin. */
addListener(window, "load", function() {
    loadPlugin();
});
