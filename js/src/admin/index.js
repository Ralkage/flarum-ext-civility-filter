import app from 'flarum/admin/app';
import CivilityLogPage from './components/CivilityLogPage';

app.initializers.add('ralkage/civility-filter', () => {
  app.extensionData
    .for('ralkage-civility-filter')
    .registerPermission(
      {
        icon: 'fas fa-shield-alt',
        label: app.translator.trans('ralkage-civility-filter.admin.permissions.bypass_label'),
        permission: 'ralkage-civility-filter.bypass',
      },
      'moderate',
      95
    )
    .registerPage(CivilityLogPage);
});
