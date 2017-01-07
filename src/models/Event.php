<?php

namespace lo\plugins\models;

use lo\plugins\components\BasePlugin;
use lo\plugins\models\query\EventQuery;
use lo\plugins\validators\JsonValidator;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%plugins__event}}".
 *
 * @property integer $id
 * @property integer $plugin_id
 * @property integer $app_id
 * @property string $trigger_class
 * @property string $trigger_event
 * @property string $handler_class
 * @property string $handler_method
 * @property string $data
 * @property integer $status
 * @property Plugin $plugin
 */
class Event extends ActiveRecord
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%plugins__event}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['plugin_id', 'app_id'], 'required'],
            [['plugin_id', 'status', 'pos'], 'integer'],
            [['trigger_class', 'trigger_event', 'handler_class', 'handler_method'], 'string', 'max' => 255],
            [['data'], JsonValidator::class]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('plugin', 'ID'),
            'app_id' => Yii::t('plugin', 'App ID'),
            'plugin_id' => Yii::t('plugin', 'Plugin ID'),
            'trigger_class' => Yii::t('plugin', 'Trigger Class'),
            'trigger_event' => Yii::t('plugin', 'Trigger Event'),
            'handler_class' => Yii::t('plugin', 'Handler Class'),
            'handler_method' => Yii::t('plugin', 'Handler Method'),
            'data' => Yii::t('plugin', 'Data'),
            'pos' => Yii::t('plugin', 'Position'),
            'status' => Yii::t('plugin', 'Status'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPlugin()
    {
        return $this->hasOne(Plugin::class, ['id' => 'plugin_id']);
    }

    /**
     * @inheritdoc
     * @return EventQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new EventQuery(get_called_class());
    }

    /**
     * @param null $appId
     * @return array
     */
    public static function eventList($appId = null)
    {
        if (!$appId) return [];

        $attributes = ['trigger_class', 'trigger_event', 'plugin_id', 'pos', 'handler_method']; // handler_class
        $order = array_combine($attributes, array_fill(0, count($attributes), SORT_ASC));

        $allEvents = self::find()
            ->alias('e')
            ->innerJoinWith(['plugin p'])
            ->where(['AND',
                ['e.status' => Event::STATUS_ACTIVE],
                ['p.status' => Plugin::STATUS_ACTIVE],
                ['e.app_id' => [$appId, BasePlugin::APP_COMMON]]
            ])
            ->orderBy($order)
            ->all();

        $result = [];

        foreach ($allEvents as $data) {
            if ($data->data) {
                $handler = [[$data->handler_class, $data->handler_method], json_decode($data->data, true)];
            } else {
                $handler = [$data->handler_class, $data->handler_method];
            }
            $result[$data->trigger_class][$data->trigger_event][] = $handler;
        }

        return $result;
    }
}