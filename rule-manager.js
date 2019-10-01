"use strict";
// Дерево основных правил: управление правилами / jQuery, jsTree, jsStorage, loader, logger
var rule = {
	debug: {
		active: true,
		script: 'rule-manager.js'
	},
	yiiController: '/admin/book/rule',
	jsTreeActive: false,
	jsTreeSelector: {
		jsTree: '#jstree-rule',
		jsTreeSearch: '#jstree-rule-form input[name=string]',
		jsTreeStatus: '#jstree-rule-form input[name=status]'
	},
	jsTreeConfig: {
		core: {
			data: {
				url: '/admin/book/rule/js-tree-children',
				data: function( node ) {
					return {
						id: node.id
					};
				}
			},
			check_callback: true,
			animation: 100,
			multiple: false,
			expand_selected_onload: true,
			dblclick_toggle: true
		},
		//changed: { },
		//checkbox: { },
		//conditionalselect: { },
		contextmenu: {
			select_node: true,
			show_at_node: false,
			items: function( node ) {
				return {
					create: {
						label: 'создать потомка',
						title: 'создать элемент внутри текущей рубрики',
						action: function() {
							rule.actionTreeManageNode( 'create', node );
						},
						separator_after: true,
						icon: 'glyphicon glyphicon-plus text-primary',
						_disabled: node.type === 'правило' ? true : false
					},
					view: {
						label: 'смотреть',
						title: 'открыть элемент для просмотра',
						action: function() {
							rule.actionTreeManageNode( 'view', node );
						},
						icon: 'glyphicon glyphicon-eye-open text-primary',
						_disabled: false
					},
					edit: {
						label: 'редактировать',
						title: 'открыть элемент для редактирования',
						action: function() {
							rule.actionTreeManageNode( 'edit', node );
						},
						icon: 'glyphicon glyphicon-cog text-primary',
						_disabled: false
					},
					delete: {
						label: 'удалить',
						title: 'удалить элемент вместе с потомками',
						action: function() {
							rule.actionTreeManageNode( 'delete', node );
						},
						separator_before: true,
						icon: 'glyphicon glyphicon-trash text-danger',
						_disabled: node['original']['type'] === 'предмет' ? true : false
					},
					rename: {
						label: 'переименовать',
						title: 'переименовать элемент',
						action: function() {
							rule.actionTreeManageNode( 'rename', node );
						},
						icon: 'glyphicon glyphicon-pencil text-primary',
						_disabled: false
					},
					statusOn: {
						label: 'включить (статус «on»)',
						title: 'открыть элемент для пользователей',
						action: function() {
							rule.actionTreeManageNode( 'statusOn', node );
						},
						separator_before: true,
						icon: 'glyphicon glyphicon-plus-sign text-success',
						_disabled: node['original']['status'] === 'on' ? true : false
					},
					statusHidden: {
						label: 'скрыть (статус «hidden»)',
						title: 'скрыть элемент от пользователей',
						action: function() {
							rule.actionTreeManageNode( 'statusHidden', node );
						},
						icon: 'glyphicon glyphicon-minus-sign text-warning',
						_disabled: node['original']['status'] === 'hidden' ? true : false
					},
					statusOff: {
						label: 'отключить (статус «off»)',
						title: 'переместить элемент в корзину',
						action: function() {
							rule.actionTreeManageNode( 'statusOff', node );
						},
						icon: 'glyphicon glyphicon-remove-sign text-danger',
						_disabled: node['original']['status'] === 'off' ? true : false
					},
					linkRuleUnit: {
						label: 'юниты (атомарные правила)',
						title: 'посмотреть/установить связь с юнитами',
						action: function() {
							rule.actionTreeManageNode( 'linkRuleUnit', node );
						},
						separator_before: true,
						icon: 'glyphicon glyphicon-link text-primary',
						_disabled: node.type === 'правило' ? false : true
					}
				};
			}
		},
		dnd: {
			copy: false,
			open_timeout: 1000,
			is_draggable: function( node, event ) {
				return node[0].parent === '#' ? false : true;
			},
			check_while_dragging: true,
			always_copy: false,
			inside_pos: 'last',
			drag_selection: false,
			touch: true, // false, 'selected'
			large_drag_target: true,
			large_drop_target: true,
			use_html5: false
		},
		massload: {
			url: '/admin/book/rule/js-tree-children-mass',
			data: function( nodes ) {
				return {
					ids: nodes.join( ',' )
				};
			}
		},
		search: {
			ajax: {
				url: '/admin/book/rule/js-tree-search' // ?str=[поисковая строка]
			},
			show_only_matches: true
		},
		//sort: { },
		state: {
			key: 'jstree-rule',
			ttl: 12 * 60 * 60 * 1000 // миллисекунды
		},
		types: {
			'#': {
				icon: 'glyphicon glyphicon-king text-info',
				valid_children: [ 'предмет' ]
			},
			'предмет': {
				icon: 'glyphicon glyphicon-briefcase text-info',
				valid_children: [ 'УМК' ]
			},
			'УМК': {
				icon: 'glyphicon glyphicon-folder-open text-info',
				valid_children: [ 'тема' ]
			},
			'тема': {
				icon: 'glyphicon glyphicon-folder-open text-info',
				valid_children: [ 'учебник' ]
			},
			'учебник': {
				icon: 'glyphicon glyphicon-book text-info',
				valid_children: [ 'правило' ]
			},
			'правило': {
				icon: 'glyphicon glyphicon-file text-info',
				valid_children: [ ]
			},
			default: {
				icon: 'glyphicon glyphicon-question-sign warning',
				valid_children: [ ]
			}
		},
		//unique: { },
		//wholerow: { },
		plugins: [
			/* 'changed', 'checkbox', 'conditionalselect', */ 'contextmenu', 'dnd', 'massload',
			'search', /* 'sort', */ 'state', 'types', 'unique', 'wholerow'
		]
	},
	linkRuleUnit: function( node ) {
		this.clog( 'linkRuleUnit', node );
		linkRuleUnit.actionInit( node );
	}
};
rule.__proto__ = jsTreeModel;
$( document ).ready( rule.actionTreeInit() );
