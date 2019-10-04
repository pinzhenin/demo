"use strict";
// Прототип для jstree
var jsTreeModel = {
	actionTreeInit: function() {
		this.clog( 'actionTreeInit' );
		this.actionTreeOpen();
	},
	actionTreeOpen: function() {
		this.clog( 'actionTreeOpen' );
		if( this.jsTreeActive ) {
			this.actionTreeRefresh();
			return;
		}
		//	rule.jsTree.action.statusRestore();
		this.eventListen();
		$( this.jsTreeSelector['jsTree'] ).jstree( this.jsTreeConfig );
		this.jsTreeActive = true;
	},
	actionTreeRefresh: function() {
		this.clog( 'actionTreeRefresh' );
		$( this.jsTreeSelector['jsTree'] ).trigger( 'before_refresh.jstree' );
		$( this.jsTreeSelector['jsTree'] ).jstree( true ).refresh();
	},
	actionTreeDestroy: function() {
		this.clog( 'actionTreeDestroy' );
		$( this.jsTreeSelector['jsTree'] ).jstree( true ).destroy();
	},
	// Поиск по названию раздела
	actionTreeSearch: function( event ) {
		var str = $( this.jsTreeSelector['jsTreeSearch'] ).val();
		this.clog( 'actionTreeSearch', str );
		if( str.trim().length < 2 ) {
			alert( 'В поисковом запросе должно быть не менее двух символов.' );
			return false;
		}
		$( this.jsTreeSelector['jsTree'] ).trigger( 'before_search.jstree' );
		$( this.jsTreeSelector['jsTree'] ).jstree( true ).search( str );
		return false;
	},
	actionTreeSearchClear: function() {
		this.clog( 'actionTreeSearchClear' );
		$( this.jsTreeSelector['jsTree'] ).jstree( true ).clear_search();
		$( this.jsTreeSelector['jsTreeSearch'] ).val( '' );
	},
	// Операции с разделами (внешние)
	actionTreeManageNode: function( action, node ) {
		this.clog( 'actionTreeManageNode', {
			action: action,
			node: node
		} );
		switch( action ) {
			case 'delete':
				if( !confirm( 'Удалить «' + node.text + '»?' ) ) {
					break;
				}
			case 'create':
			case 'view':
			case 'edit':
				$( location ).attr( 'href', this.yiiController + '/' + action + '/' + node.id );
				break;
			case 'rename':
				this.actionTreeManageNodeChangeName( node );
				break;
			case 'statusOn':
			case 'statusHidden':
			case 'statusOff':
				this.actionTreeManageNodeChangeStatus( node, action.substr( 6 ).toLowerCase() );
				break;
			case 'move':
				this.actionTreeManageNodeMove( node );
				break;
			case 'refresh':
				var id = node;
				if( id === '#' ) {
					this.actionTreeRefresh();
				}
				else {
					$( this.jsTreeSelector['jsTree'] ).trigger( 'before_refresh_node.jstree' );
					$( this.jsTreeSelector['jsTree'] ).jstree( true ).refresh_node( id );
				}
				break;
			default:
				if( typeof this[action] === 'function' ) {
					return this[action]( node );
				}
				alert( 'Не найден обработчик «' + action + '»' );
		}
	},
	actionTreeManageNodeChangeName: function( node ) {
		this.clog( 'actionTreeManageNodeChangeName', node );
		var me = this;
		$( this.jsTreeSelector['jsTree'] ).jstree( true ).edit( node, null,
			function( node, renameSuccess, renameCancel ) {
				me.clog( 'actionTreeManageNodeChangeName', {
					'node': node,
					'renameSuccess': renameSuccess,
					'renameCancel': renameCancel
				} );
				if( renameSuccess === false ) { // ошибка переименования
					alert( 'Не удалось переименовать: jsTree error! (см. console.log)' );
					return false;
				}
				else if( renameCancel === true ) { // отмена пользователем
					return false;
				}
				$.ajax( {
					'method': 'GET',
					'url': me.yiiController + '/change-name',
					'data': {
						'id': node.id,
						'name': node.text
					},
					'dataType': 'json'
				} )
					.done( function( data ) {
						if( !data['result']['success'] ) {
							alert( 'Не удалось переименовать: model error! (см. console.log)\n' + JSON.stringify( data['result']['errors'] ) );
						}
					} )
					.fail( function() {
						alert( 'Не удалось переименовать: server error! (см. console.log)' );
					} )
					.always( function( data, textStatus, jqXHR ) {
						me.clogAjax( 'actionTreeManageNodeChangeName', data, textStatus, jqXHR );
						me.actionTreeManageNode( 'refresh', node.parent );
					} );
			}
		);
	},
	actionTreeManageNodeChangeStatus: function( node, status ) {
		this.clog( 'actionTreeManageNodeChangeStatus', {
			node: node,
			status: status
		} );
		var me = this;
		$.ajax( {
			'method': 'GET',
			'url': me.yiiController + '/change-status',
			'data': {
				'id': node.id,
				'status': status
			},
			'dataType': 'json'
		} )
			.done( function( data ) {
				if( !data['result']['success'] ) {
					alert( 'Не удалось поменять статус: model error! (см. console.log)\n' + JSON.stringify( data['result']['errors'] ) );
				}
			} )
			.fail( function() {
				alert( 'Не удалось поменять статус: server error! (см. console.log)' );
			} )
			.always( function( data, textStatus, jqXHR ) {
				me.clogAjax( 'actionTreeManageNodeChangeStatus', data, textStatus, jqXHR );
				me.actionTreeManageNode( 'refresh', node.parent );
			} );
	},
	actionTreeManageNodeMove: function( obj ) {
		this.clog( 'actionTreeManageNodeMove', obj );
		var me = this;
		$.ajax( {
			'method': 'GET',
			'url': me.yiiController + '/move',
			'data': {
				'id': obj.node.id,
				'idParentNew': obj.parent,
				'positionNew': obj.position,
				'idParentOld': obj.old_parent,
				'positionOld': obj.old_position
			},
			'dataType': 'json'
		} )
			.done( function( data ) {
				if( !data['result']['success'] ) {
					alert( 'Не удалось переместить: model error! (см. console.log)\n' + JSON.stringify( data['result']['errors'] ) );
				}
			} )
			.fail( function() {
				alert( 'Не удалось переместить: server error! (см. console.log)' );
			} )
			.always( function( data, textStatus, jqXHR ) {
				me.clogAjax( 'actionTreeManageNodeMove', data, textStatus, jqXHR );
				me.actionTreeManageNode( 'refresh', obj.parent );
				if( obj.parent !== obj.old_parent ) {
					me.actionTreeManageNode( 'refresh', obj.old_parent );
				}
			} );
	},
	// Обработка событий
	eventListen: function() {
		this.clog( 'eventListen' );
		var events = [
			'init.jstree', 'loading.jstree', 'destroy.jstree', 'loaded.jstree', 'ready.jstree',
			'load_node.jstree', 'load_all.jstree', 'model.jstree', 'redraw.jstree',
			'before_open.jstree', 'open_node.jstree', 'after_open.jstree',
			'close_node.jstree', 'after_close.jstree', 'open_all.jstree', 'close_all.jstree',
			'enable_node.jstree', 'disable_node.jstree', 'hide_node.jstree', 'show_node.jstree',
			'hide_all.jstree', 'show_all.jstree', 'activate_node.jstree',
			/* 'hover_node.jstree', 'dehover_node.jstree', */ 'select_node.jstree', 'changed.jstree',
			'deselect_node.jstree', 'select_all.jstree', 'deselect_all.jstree',
			'set_state.jstree', 'refresh.jstree', 'refresh_node.jstree', 'set_id.jstree', 'set_text.jstree',
			'create_node.jstree', 'rename_node.jstree', 'delete_node.jstree',
			'move_node.jstree', 'copy_node.jstree', 'cut.jstree', 'copy.jstree', 'paste.jstree',
			'clear_buffer.jstree', 'set_thethis.jstree', /* 'show_stripes.jstree', 'hide_stripes.jstree',
			 'show_dots.jstree', 'hide_dots.jstree', 'show_icons.jstree', 'hide_icons.jstree',
			 'show_ellipsis.jstree', 'hide_ellipsis.jstree', */
			// changed plugin
			'changed.jstree',
			// checkbox plugin
			'disable_checkbox.jstree', 'enable_checkbox.jstree', 'check_node.jstree', 'uncheck_node.jstree', 'check_all.jstree', 'uncheck_all.jstree',
			// contextmenu plugin
			'show_contextmenu.jstree', 'context_parse.vakata', 'context_show.vakata', 'context_hide.vakata',
			// dnd plugin
			'dnd_scroll.vakata', 'dnd_start.vakata', 'dnd_move.vakata', 'dnd_stop.vakata',
			// search plugin
			'search.jstree', 'clear_search.jstree', 'state_ready.jstree',
			// additional events for starting loader
			'before_refresh.jstree', 'before_refresh_node.jstree', 'before_search.jstree'
		].join( ' ' );
		var me = this;
		$( this.jsTreeSelector['jsTree'] ).on( events,
			function( event, data ) {
				me.eventHandle( me, event, data );
			} );
	},
	eventRoute: {
		pattern: [
			[ 'init', 'loading', 'state_ready' ],
//			[ 'init', 'loading', 'ready' ],
			[ 'before_refresh', 'refresh' ],
			[ 'before_refresh_node', 'refresh_node' ],
			[ 'before_search', 'load_node', 'open_node', 'search', 'redraw' ],
			[ 'before_search', 'search' ]
		],
		current: [ ]
	},
	eventHandle: function( me, event, data ) {
		me.clog( 'eventHandle', event );
		switch( event.type ) {
			case 'move_node':
				me.actionTreeManageNode( 'move', data );
		}
		var routeFinish = false;
		var routeCandidate = $.extend( true, [ ],
			me.eventRoute.current.length ? me.eventRoute.current : me.eventRoute.pattern );
		var routeMatch = [ ];
		while( routeCandidate.length ) {
			var route = routeCandidate.shift();
			if( route[0] === event.type && route.length === 1 ) {
				routeFinish = true;
				break;
			}
			else if( route[0] === event.type ) {
				route.shift();
				routeMatch.push( route );
			}
		}
		if( routeFinish ) {
			me.eventRoute.current = [ ];
			me.actionLoaderHide();
			return;
		}
		if( routeMatch.length ) {
			me.actionLoaderShow();
			me.eventRoute.current = routeMatch;
		}
	},
	// loader
	actionLoaderLoad: function() {
		this.clog( 'actionLoaderLoad' );
		this.loader = false;
		if( typeof Loader === 'function' ) {
			this.loader = new Loader() || false;
		}
	},
	actionLoaderShow: function() {
		this.clog( 'actionLoaderShow' );
		if( typeof this.loader === 'undefined' ) {
			this.actionLoaderLoad();
		}
		if( typeof this.loader === 'object' && typeof this.loader.show === 'function' ) {
			this.loader.show();
		}
	},
	actionLoaderHide: function() {
		this.clog( 'actionLoaderHide' );
		if( typeof this.loader === 'undefined' ) {
			this.actionLoaderLoad();
		}
		if( typeof this.loader === 'object' && typeof this.loader.hide === 'function' ) {
			this.loader.hide();
		}
	},
	// logger: console.log()
	clog: function( action, data ) {
		if( typeof this.logger === 'undefined' && typeof Logger === 'function' ) {
			this.logger = new Logger( this.debug ) || false;
		}
		if( typeof this.logger === 'object' && typeof this.logger.log === 'function' ) {
			this.logger.log( action, data );
		}
	},
	clogAjax: function( action, data, textStatus, jqXHR ) {
		if( typeof this.logger === 'undefined' && typeof Logger === 'function' ) {
			this.logger = new Logger( this.debug ) || false;
		}
		if( typeof this.logger === 'object' && typeof this.logger.logAjax === 'function' ) {
			this.logger.logAjax( action, data, textStatus, jqXHR );
		}
	}
};
