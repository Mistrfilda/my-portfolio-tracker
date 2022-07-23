import {LiveForm, Nette} from 'live-form-validation';

LiveForm.addError = function (el, message) {
    // Ignore elements with disabled live validation
    if (this.hasClass(el, this.options.disableLiveValidationClass))
        return;

    var groupEl = this.getGroupElement(el);
    this.setFormProperty(el.form, "hasError", true);
    this.addClass(groupEl, this.options.controlErrorClass);

    // let groupSelector = $(el);
    // if (groupSelector.is("select")) {
    //     // let selectButton = groupSelector.parent().children('button');
    //     // selectButton.removeClass('btn-primary');
    //     // selectButton.addClass('btn-danger');
    // }

    if (this.options.showValid) {
        this.removeClass(groupEl, this.options.controlValidClass);
    }

    if (!message) {
        message = '&nbsp;';
    } else {
        message = this.options.messageErrorPrefix + message;
    }

    var messageEl = this.getMessageElement(el);
    messageEl.innerHTML = message;
    messageEl.className = this.options.messageErrorClass;
};

LiveForm.removeError = function (el) {
    // We don't want to remove any errors during onLoadValidation
    if (this.getFormProperty(el.form, "onLoadValidation"))
        return;

    var groupEl = this.getGroupElement(el);

    // let groupSelector = $(el);
    // if (groupSelector.is("select")) {
    //     let selectButton = groupSelector.parent().children('button');
    //     selectButton.removeClass('btn-danger');
    //     selectButton.addClass('btn-primary');
    // }

    this.removeClass(groupEl, this.options.controlErrorClass);

    var id = el.getAttribute('data-lfv-message-id');
    if (id) {
        var messageEl = this.getMessageElement(el);
        messageEl.innerHTML = '';
        messageEl.className = '';
    }

    if (this.options.showValid) {
        if (this.showValid(el))
            this.addClass(groupEl, this.options.controlValidClass);
        else
            this.removeClass(groupEl, this.options.controlValidClass);
    }
};

LiveForm.getMessageElement = function (el) {
    // For multi elements (with same name) work only with first element attributes
    if (el.name && el.name.match(/\[\]$/)) {
        el = el.form.elements[el.name].tagName ? el : el.form.elements[el.name][0];
    }

    var id = el.getAttribute('data-lfv-message-id');
    if (!id) {
        // ID is not specified yet, let's create a new one
        id = this.getMessageId(el);

        // Remember this id for next use
        el.setAttribute('data-lfv-message-id', id);
    }

    var messageEl = document.getElementById(id);
    if (!messageEl) {

        let messageTag = this.options.messageTag;
        if (el.type === 'file') {
            messageTag = 'div';
        }

        // Message element doesn't exist, lets create a new one
        messageEl = document.createElement(messageTag);
        messageEl.id = id;
        if (el.style.display == 'none' && !this.hasClass(el, this.options.enableHiddenMessageClass)) {
            messageEl.style.display = 'none';
        }

        var parentEl = this.getMessageParent(el);
        if (parentEl === el.parentNode) {
            parentEl.insertBefore(messageEl, el.nextSibling);
        } else if (parentEl) {
            typeof parentEl.append === 'function' ? parentEl.append(messageEl) : parentEl.appendChild(messageEl);
        }
    }

    return messageEl;
};

LiveForm.setOptions({
    showMessageClassOnParent: "form-control",
    messageParentClass: false,
    controlErrorClass: 'form-input-error',
    controlValidClass: 'has-success',
    enableHiddenMessageClass: 'show-hidden-error',
    disableLiveValidationClass: 'no-live-validation',
    disableShowValidClass: 'no-show-valid',
    messageTag: 'span',
    messageErrorClass: 'span-form-error',
    messageIdPostfix: '_message',
    messageErrorPrefix: '',
    showAllErrors: true,
    showValid: false,
    wait: false,
    focusScreenOffsetY: false
});

Nette.initOnLoad();
window.Nette = Nette;
window.LiveForm = LiveForm;