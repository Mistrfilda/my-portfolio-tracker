const colors = require("tailwindcss/colors");

module.exports = {
	content: [
		'./src/**/*.html',
		'./src/**/*.latte',
		'./assets/**/*.html',
		'./assets/**/*.latte',
		'./assets/**/*.ts',
		'./assets/**/*.js'
	],
	theme: {
		extend: {
			colors: {
				orange: colors.orange,
				sky: colors.sky,
				emerald: colors.emerald,
				teal: colors.teal,
				cyan: colors.cyan,
				indigo: colors.indigo,
				rose: colors.rose,
			}
		}
	},
	variants: {
		extend: {
			fontWeight: ["hover", "focus"]
		}
	},
};
