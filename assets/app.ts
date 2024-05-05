declare global {
    interface Window {
        frontMenu: any;
        dropdown: any;
        Alpine: any;
        flashMessage: any;
        datagridFilter: any;
        photosModal: any;
        modal: any;
        loadChart: any;
        expenseMainTag: any;
        addExpenseOtherTag: any;
        removeOtherTag: any;
        currencyConvert: any;
    }
}

//styles
import './scss/index.scss';

import './js/LiveFormValidation';

import './ts/alpine/AppAlpine';

import './ts/select/select';
