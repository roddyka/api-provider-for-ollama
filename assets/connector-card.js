/**
 * Ollama Local — Custom connector card.
 *
 * Registers a custom React render function for the "ollama-local" connector
 * on the wp-admin Connectors screen, so the card shows Ollama-specific
 * configuration (Base URL, Default Model) instead of the default API Key form.
 *
 * @package WordPress\OllamaLocalAiProvider
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const wp = window.wp || {};

if ( wp.element && wp.components && wp.apiFetch && wp.i18n ) {
	const { createElement: h, useState } = wp.element;
	const {
		TextControl,
		Button,
		Notice,
		__experimentalVStack: VStack,
		__experimentalHStack: HStack,
	} = wp.components;
	const apiFetch = wp.apiFetch;
	const { __, sprintf } = wp.i18n;

	let data = {};
	try {
		const node = document.getElementById(
			'wp-script-module-data-ai-provider-for-ollama-local/connector'
		);
		if ( node && node.textContent ) {
			data = JSON.parse( node.textContent ) || {};
		}
	} catch ( e ) {
		data = {};
	}

	const initialBaseUrl = typeof data.baseUrl === 'string' ? data.baseUrl : '';
	const initialModel =
		typeof data.defaultModel === 'string' ? data.defaultModel : '';
	const defaultBaseUrlPlaceholder =
		typeof data.defaultBaseUrl === 'string' && data.defaultBaseUrl
			? data.defaultBaseUrl
			: 'http://127.0.0.1:11434/v1';
	const models = Array.isArray( data.models ) ? data.models : [];
	const restPath =
		typeof data.restPath === 'string' && data.restPath
			? data.restPath
			: '/ai-provider-for-ollama-local/v1/settings';
	const reachable = !! data.reachable;
	const statusMessage =
		typeof data.statusMessage === 'string' ? data.statusMessage : '';
	const effectiveBaseUrl =
		typeof data.effectiveBaseUrl === 'string' && data.effectiveBaseUrl
			? data.effectiveBaseUrl
			: defaultBaseUrlPlaceholder;

	const isConfigured = !! initialModel || !! initialBaseUrl;

	function OllamaCard( props ) {
		const [ isExpanded, setIsExpanded ] = useState( false );
		const [ baseUrl, setBaseUrl ] = useState( initialBaseUrl );
		const [ model, setModel ] = useState( initialModel );
		const [ saving, setSaving ] = useState( false );
		const [ error, setError ] = useState( null );
		const [ saved, setSaved ] = useState( false );
		const [ effective, setEffective ] = useState( effectiveBaseUrl );
		const [ savedBaseUrl, setSavedBaseUrl ] = useState( initialBaseUrl );
		const [ savedModel, setSavedModel ] = useState( initialModel );
		const hasSavedConfig = !! savedBaseUrl || !! savedModel;

		const onSave = async () => {
			setSaving( true );
			setError( null );
			setSaved( false );
			try {
				const response = await apiFetch( {
					path: restPath,
					method: 'POST',
					data: {
						base_url: baseUrl,
						default_model: model,
					},
				} );
				if (
					response &&
					typeof response.effective_base_url === 'string'
				) {
					setEffective( response.effective_base_url );
				}
				setSavedBaseUrl( baseUrl );
				setSavedModel( model );
				setSaved( true );
				setIsExpanded( false );
			} catch ( e ) {
				setError(
					( e && e.message ) ||
						__(
							'Could not save Ollama settings.',
							'ai-provider-for-ollama-local'
						)
				);
			} finally {
				setSaving( false );
			}
		};

		const onCancel = () => {
			setBaseUrl( savedBaseUrl );
			setModel( savedModel );
			setError( null );
			setIsExpanded( false );
		};

		const datalistId = 'ollama-local-models-list';

		const statusNotice = reachable
			? h(
					Notice,
					{ status: 'success', isDismissible: false },
					models.length > 0
						? sprintf(
								/* translators: %s: comma-separated list of local model IDs. */
								__(
									'Reachable — local models: %s',
									'ai-provider-for-ollama-local'
								),
								models.join( ', ' )
						  )
						: __(
								'Reachable — no local models detected yet.',
								'ai-provider-for-ollama-local'
						  )
			  )
			: h(
					Notice,
					{ status: 'warning', isDismissible: false },
					sprintf(
						/* translators: %s: diagnostic status string. */
						__(
							'Ollama unreachable — status: %s',
							'ai-provider-for-ollama-local'
						),
						statusMessage || 'unknown'
					)
			  );

		const baseUrlField = h( TextControl, {
			__next40pxDefaultSize: true,
			label: __( 'Base URL', 'ai-provider-for-ollama-local' ),
			value: baseUrl,
			onChange: setBaseUrl,
			placeholder: defaultBaseUrlPlaceholder,
			help: sprintf(
				/* translators: %s: effective base URL currently in use. */
				__(
					'Leave empty to use the default. Effective: %s',
					'ai-provider-for-ollama-local'
				),
				effective
			),
			disabled: saving,
		} );

		const modelField = h(
			'div',
			null,
			h( TextControl, {
				__next40pxDefaultSize: true,
				label: __(
					'Default Model',
					'ai-provider-for-ollama-local'
				),
				value: model,
				onChange: setModel,
				placeholder: 'llama3.1',
				list: datalistId,
				help: __(
					'Model used as the preferred text model. Must match an installed local model.',
					'ai-provider-for-ollama-local'
				),
				disabled: saving,
			} ),
			models.length > 0 &&
				h(
					'datalist',
					{ id: datalistId },
					models.map( ( m ) =>
						h( 'option', { key: m, value: m } )
					)
				)
		);

		const children = isExpanded
			? h(
					VStack,
					{ spacing: 3 },
					statusNotice,
					baseUrlField,
					modelField,
					error &&
						h(
							Notice,
							{
								status: 'error',
								isDismissible: true,
								onRemove: () => setError( null ),
							},
							error
						),
					h(
						HStack,
						{ justify: 'flex-start' },
						h(
							Button,
							{
								__next40pxDefaultSize: true,
								variant: 'primary',
								onClick: onSave,
								isBusy: saving,
								disabled: saving,
								accessibleWhenDisabled: true,
							},
							__( 'Save', 'ai-provider-for-ollama-local' )
						),
						h(
							Button,
							{
								__next40pxDefaultSize: true,
								variant: 'tertiary',
								onClick: onCancel,
								disabled: saving,
							},
							__(
								'Cancel',
								'ai-provider-for-ollama-local'
							)
						)
					)
			  )
			: saved
			? h(
					Notice,
					{
						status: 'success',
						isDismissible: true,
						onRemove: () => setSaved( false ),
					},
					__(
						'Settings saved.',
						'ai-provider-for-ollama-local'
					)
			  )
			: null;

		const actionArea = h(
			Button,
			{
				__next40pxDefaultSize: true,
				variant: hasSavedConfig ? 'tertiary' : 'secondary',
				onClick: () => setIsExpanded( ( v ) => ! v ),
				'aria-expanded': isExpanded,
			},
			isExpanded
				? __( 'Close', 'ai-provider-for-ollama-local' )
				: hasSavedConfig
				? __( 'Edit', 'ai-provider-for-ollama-local' )
				: __( 'Set up', 'ai-provider-for-ollama-local' )
		);

		return h(
			ConnectorItem,
			{
				name: props.name,
				description: props.description,
				logo: props.logo,
				actionArea,
			},
			children
		);
	}

	registerConnector( 'ollama-local', {
		render: OllamaCard,
	} );
}
