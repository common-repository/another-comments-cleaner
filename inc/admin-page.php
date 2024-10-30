<?php
if ( !defined( 'WPINC' ) ) return;
$options = get_option( self::optname, array( 'map' => array() ) );
$default_map = array(
	'pending' => 'ignore',
	'spam' => 'delete',
	'trash' => 'ignore',
);
$default_data = array(
	'lastExec' => 0,
	'lastTrash' => 0,
	'lastDelete' => 0,
	'lastIgnore' => 0,
	'period' => 'none',
	'nSched' => 2,
	'hSched' => 12,
	'mSched' => 30,
);

$map = empty( $options['map'] )? $default_map : $options['map'];
$options += $default_data;

?><div class="wrap" id="ancc">
	<h3><?php _e( 'Cleaning Tasks', 'ancc' ); ?></h3>
	<div id="last-exec">
		<h4><?php _e( 'Last execution', 'ancc' ); ?></h4>
		<p><strong><?php ancc_e( 'Date' ); ?></strong>:&nbsp;<code class="execDate"><?php echo ( 0 < $options['lastExec'] )? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' \U\T\C', $options['lastExec'] ): 'N/A';?></code></p>
		<p><strong><?php echo _n( 'Deleted', 'Deleted', $options['lastDelete'], 'ancc' ) ?></strong>:&nbsp;<code class="lastDelete"><?php echo $options['lastDelete'] ?></code></p>
		<p><strong><?php echo _n( 'Moved to trash', 'Moved to trash', $options['lastTrash'], 'ancc' ) ?></strong>:&nbsp;<code class="lastTrash"><?php echo $options['lastTrash'] ?></code></p>
		<p><strong><?php echo _n( 'Ignored', 'Ignored', $options['lastIgnore'], 'ancc' ) ?></strong>:&nbsp;<code class="lastIgnore"><?php echo $options['lastIgnore'] ?></code></p>
		<p><strong><?php _e( 'Next task' ) ?></strong>:&nbsp;<code class="nextRun"><?php echo $this->next_sched_readable() ?></code></p>
	</div>
	<form method="post" id="clean">
		<h4><?php _e( 'Clean immedately', 'ancc' ); ?></h4>
		<p><input type="submit" class="button-primary" name="clean" value="<?php echo esc_attr( __( 'Clean now', 'ancc' ) ); ?>" /></p>
		<p class="description"><i class="dashicons dashicons-info"></i><?php _e( 'If you edited the bulk actions fields bellow without saving the changes, the currently display actions will be taken, not the settings stored in the plugin&#39;s options', 'ancc' ); ?></p>
	</form>
	<form method="post" id="actionsMap">
		<h4><?php ancc_e( 'Bulk Actions' ) ?></h4>
		<table class="widefat">
			<thead>
				<th><?php ancc_e( 'Comment status' ) ?></th>
				<th><?php ancc_e( 'Bulk Actions' ) ?></th>
			</thead>
			<tbody>
				<?php foreach ( $map as $k => $v ) : ?>
				<?php $alt = ( isset( $alt ) && !empty( $alt ) )? '' : 'class="alternate"'; ?>
				<tr <?php echo $alt;  ?>>
					<td><code><?php echo $this->comment_status[$k] ?></code></td>
					<td><select name="map[<?php echo $k ?>]">
						<?php foreach ( $this->comment_action as $x => $y ) : ?>
							<?php if ( 'spam' == $k && 'trash' == $x ) continue; ?>
							<?php if ( 'trash' == $k && 'trash' == $x ) continue; ?>
							<option value="<?php echo $x ?>" <?php selected( $x, $v ) ?>><?php echo $y ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="submit"><input class="button-primary" type="submit" name="save-map" value="<?php echo esc_attr( ancc__( 'Save Changes' ) ); ?>" /></p>
	</form>
	<form method="post" id="sched">
		<h4><?php _e( 'Cleaning tasks Schedule', 'ancc' ) ?></h4>
		<input type="radio" name="period" id="radio-disable" value="disabled" <?php checked( 'none', $options['period'] ) ?> />
		<strong><?php _e( 'Disabled', 'ancc' ); ?></strong>
		<input class="radio-fields fields-disable" <?php echo ( 'none' != $options['period'] )? 'disabled': ''; checked( 'none' == $options['period'] ); ?> type="hidden" id="period-disable" value="1" />
		<hr />
		<input type="radio" name="period" id="radio-default" value="default" <?php checked( in_array( $options['period'], array( 'hourly', 'daily', 'twicedaily' ) ) ) ?> />
		<strong><?php _e( 'Default periods', 'ancc' ); ?></strong><br /><br />
		<select class="radio-fields fields-default" name="period-default" <?php echo ( !in_array( $options['period'], array( 'hourly', 'daily', 'twicedaily' ) ) )? 'disabled' : '' ?> >
			<option value="hourly" <?php selected( $options['period'], 'hourly' ) ?>><?php _e( 'hourly', 'ancc' ) ?></option>
			<option value="daily" <?php selected( $options['period'], 'daily' ) ?>><?php _e( 'daily', 'ancc' ) ?></option>
			<option value="twicedaily" <?php selected( $options['period'], 'twicedaily' ) ?>><?php _e( 'twice daily', 'ancc' ) ?></option>
		</select>
		<hr />
		<input type="radio" name="period" id="radio-custom" value="custom" <?php checked( $options['period'], 'custom' ) ?> />
		<strong><?php _e( 'At a given hour, every N days', 'ancc' ); ?></strong><br />
		<br />
		<strong>N</strong>:&nbsp;
		<input <?php echo ( 'custom' != $options['period'] )? 'disabled' : '';  checked( 'custom' == $options['period'] ); ?> class="radio-fields fields-custom" name="custom-n" id="custom-n" type="number" min="1" value="<?php echo $options['nSched'] ?>" max="365" />
		<br />
		<br />
		<strong><?php _e( 'Time', 'ancc' ) ?></strong>:&nbsp;
		<input <?php echo ( 'custom' != $options['period'] )? 'disabled' : ''; ?> class="radio-fields fields-custom" type="number" name="custom-h" min="0" max="23" value="<?php echo $options['hSched'] ?>" />&nbsp;:&nbsp;
		<input <?php echo ( 'custom' != $options['period'] )? 'disabled' : ''; ?> class="radio-fields fields-custom" type="number" name="custom-m" min="0" max="59" value="<?php echo $options['mSched'] ?>" />
		<p class="description"><?php _e( '24 hours format UTC time', 'ancc' ); ?></p>
		<p class="submit"><input class="button-primary" type="submit" name="save-period" value="<?php echo esc_attr( ancc__( 'Save Changes' ) ); ?>" /></p>
	</form>
</div>