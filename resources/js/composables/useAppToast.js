export function pushToast(detail = {}) {
    window.dispatchEvent(new CustomEvent("app-toast", { detail }));
}

export function successToast(message, title = "Success") {
    pushToast({ tone: "success", title, message });
}

export function errorToast(message, title = "Something went wrong") {
    pushToast({ tone: "error", title, message });
}

export function infoToast(message, title = "Update") {
    pushToast({ tone: "info", title, message });
}

export function warningToast(message, title = "Attention") {
    pushToast({ tone: "warning", title, message });
}
