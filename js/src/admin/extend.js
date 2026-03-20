import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import CivilityLogPage from './components/CivilityLogPage';

export default [
  new Extend.Admin()
    .permission(
      () => ({
        icon: 'fas fa-shield-alt',
        label: app.translator.trans('ralkage-civility-filter.admin.permissions.bypass_label'),
        permission: 'ralkage-civility-filter.bypass',
      }),
      'moderate',
      95
    )
    .page(CivilityLogPage),
];
