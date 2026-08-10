declare global {
    interface Window {
        frontMenu: unknown;
        dropdown: unknown;
        Alpine: unknown;
        flashMessage: unknown;
        datagridFilter: unknown;
        photosModal: unknown;
        modal: unknown;
        loadChart: unknown;
        expenseMainTag: unknown;
        addExpenseOtherTag: unknown;
        removeOtherTag: unknown;
        currencyConvert: unknown;
        stockValuationModelData: unknown;
        dragScroll: unknown;
    }
}

//styles
import 'tom-select/dist/css/tom-select.default.css';
import './css/index.css';

import './js/LiveFormValidation';

import './ts/alpine/AppAlpine';

import './ts/select/select';

import '@tailwindplus/elements';
