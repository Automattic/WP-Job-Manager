
/**
 * WordPress Dependencies.
 */
const { __, sprintf } = wp.i18n,
	{ Dashicon } = wp.components;

/**
 * JobPlaceholder component.
 */
export default function JobPlaceholderList( { number, className } ) {
	const placeholders = [];

	const classPrefix = 'job-placeholder-list';

	for ( let i = 1; i <= number; i++ ) {
		placeholders.push(
			<div className={ `${classPrefix}__job` }>
				<div className={ `${classPrefix}__icon` }>
					<Dashicon icon="building" />
				</div>
				<div className={ `${classPrefix}__name` }>
					{ sprintf( __( "Job %d" ), i ) }
				</div>
				<div className={ `${classPrefix}__place` }>
					{ __( "Place" ) }
				</div>
				<div className={ `${classPrefix}__type-date` }>
					<p className={ `${classPrefix}__type` }>
						{ __( "Type" ) }
					</p>
					<p>{ __( "Date" ) }</p>
				</div>
			</div>
		);
	}

	return (
		<div className={ className }>
			{ placeholders }
		</div>
	);
}
