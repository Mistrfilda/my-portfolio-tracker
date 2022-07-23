import TomSelect from "tom-select";

let settings = {
    plugins: ['remove_button'],
    render: {
        item: function (data: any, escape: any) {
            console.log(data);
            return '<div class="tom-span-bg"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-white">' + escape(data.text) + '</span></div>';
        },
    }
};

let tomSelectElements: HTMLCollectionOf<Element> = document.getElementsByClassName("tom-select-selector");
let selectors = [];

for (var i = 0; i< tomSelectElements.length; i++) {
    selectors.push('#' + tomSelectElements[i].id);
}

for (var g = 0; g < selectors.length; g++) {
    new TomSelect(selectors[g], settings);
}