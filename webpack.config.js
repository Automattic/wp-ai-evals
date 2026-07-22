const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( process.cwd(), 'src/admin/index.tsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'build/admin' ),
		filename: '[name].js',
		clean: true,
	},
};
