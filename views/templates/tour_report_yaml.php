---
id: <?php $this->print_yaml($this->tour->id) ?>
area:
  id: <?php $this->print_yaml($this->tour->area->id) ?>
  name: <?php $this->print_yaml($this->tour->area->name) ?>
  point1: [<?php echo $this->tour->area->coordinate1->lat . ', ' . $this->tour->area->coordinate1->lon ?>]
  point2: [<?php echo $this->tour->area->coordinate2->lat . ', ' . $this->tour->area->coordinate2->lon ?>]
name: <?php $this->print_yaml($this->tour->name) ?>
author: <?php $this->print_yaml($this->user_service->get_user($this->tour->user_id)->user_login) ?>
intro: <?php $this->print_yaml($this->tour->intro) ?>
type: <?php $this->print_yaml($this->tour->type) ?>
walkLength: <?php $this->print_yaml($this->tour->walk_length) ?>
duration: <?php $this->print_yaml($this->tour->duration) ?>
tagWhen: [<?php echo $this->tour->tag_when_start . ', ' . $this->tour->tag_when_end ?>]
tagWhat: <?php $this->print_yaml($this->tour->tag_what) ?>
tagWhere: <?php $this->print_yaml($this->tour->tag_where) ?>
accessibility: <?php $this->print_yaml($this->tour->accessibility) ?>
track: [<?php foreach ($this->tour->coordinates as $coord): ?>
<?php echo "[$coord->lat, $coord->lon], " ?>
<?php endforeach ?>
]
mapstops:
<?php foreach ($this->tour->mapstops as $mapstop): ?>
- id: <?php $this->print_yaml($mapstop->id) ?>
  name: <?php $this->print_yaml($mapstop->name) ?>
  description: <?php $this->print_yaml($mapstop->description) ?>
  place:
    id: <?php $this->print_yaml($mapstop->place->id) ?>
    name: <?php $this->print_yaml($mapstop->place->name) ?>
    lat: <?php $this->print_yaml($mapstop->place->coordinate->lat) ?>
    lon: <?php $this->print_yaml($mapstop->place->coordinate->lon) ?>
  pages:
<?php for($i = 0; $i < count($mapstop->post_ids); $i++): ?>
<?php $page_post = get_post($mapstop->post_ids[$i]) ?>
<?php $media = get_attached_media(null, $page_post->ID) ?>
    - id: <?php $this->print_yaml($page_post->ID) ?>
      pos: <?php $this->print_yaml(($i + 1)) ?>
      guid: <?php $this->print_yaml($page_post->guid) ?>
      content: <?php $this->print_yaml($page_post->post_content) ?>
<?php if(count($media) > 0): ?>
      media:
<?php foreach ($media as $mediaitem): ?>
        - guid: <?php $this->print_yaml($mediaitem->guid) ?>
<?php endforeach // mediaitem ?>
<?php endif // media exist ?>
<?php endfor // page_posts ?>
<?php endforeach // mapstops ?>
createdAt: <?php $this->print_yaml($this->datetime_format($this->tour->created_at)) ?>
...