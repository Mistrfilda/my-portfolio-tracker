import naja, {Naja} from "naja";
import {SpinnerExtension} from "./extension/spinnerExtension";

export function registerExtensions(naja: Naja) {
    naja.registerExtension(new SpinnerExtension());
}